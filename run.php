<?php

// Import AWS SDK for PHP (autoload the AWS SDK classes)
require 'vendor/autoload.php';
require 'inc/OleeoFeedParser.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

echo 'Feed Parser Started';

$feeds = [
    [
        'id' => 'moj-oleeo-structured',
        'name' => 'MOJ Oleeo Jobs Structured Feed',
        'url' => 'https://justicejobs.tal.net/vx/mobile-0/appcentre-1/brand-2/candidate/jobboard/vacancy/3/feed/structured',
        'type' => 'complex'
    ],
    [
        'id' => 'moj-oleeo-simple',
        'name' => 'MOJ Oleeo Jobs Simple Feed',
        'url' => 'https://justicejobs.tal.net/vx/mobile-0/appcentre-1/brand-2/candidate/jobboard/vacancy/3/feed',
        'type' => 'simple'
    ],
    [
        'id' => 'hmpps-filtered-oleeo',
        'name' => 'HMPPS Filtered Oleeo Jobs Structured Feed',
        'url' => 'https://justicejobs.tal.net/vx/mobile-0/appcentre-1/brand-2/candidate/jobboard/vacancy/3/feed/structured',
        'type' => 'complex',
        'filters' => [
            [
                'fieldName' => 'organisation',
                'acceptedValues' => [
                    'HMPPS HQ',
                    'HMPPS - National Probation Service',
                    'HMPPS - Public Sector Prisons',
                    'HMPPS - Youth Custody Estate'
                ]
            ]
        ]
    ],
 ];

if (count($feeds) == 0) {
    return;
}

$availableFeeds = [];

 // AWS S3 Bucket Name
$s3BucketName = getenv('S3_UPLOADS_BUCKET');

// AWS S3 Bucket Path
$s3BucketPath = 'feed-parser/';  // Set to an empty string if the root of the bucket

// AWS S3 Region
$awsRegion = 'eu-west-2';

// Create an S3Client with the AWS credentials automatically provided by IAM instance profile
$s3Client = new S3Client([
    'region' => $awsRegion,
    'version' => 'latest'
]);


foreach ($feeds as $feed) {
    $feedID = $feed['id'];
    $feedURL = $feed['url'];
    $xmlName = "$feedID.xml";
    $xmlLocation = "/output/$xmlName";

    // Fetch the XML feed using wget
    exec("wget -O  $xmlLocation $feedURL");

    $feed_parser = new OleeoFeedParser();

    $optionalFields = [
       'id',
       'closingDate',
       'salary',
       'availablePositions',
       'organisation',
       'addresses',
       'cities',
       'regions',
       'roleTypes',
       'contractTypes'
    ];

    $filters = [];

    if(array_key_exists('filters', $feed) && !empty($feed['filters'])){
        $filters = $feed['filters'];
    }

    $jsonFile = "output/$feedID.json";

    $parseResult = $feed_parser->parseToJSON("output/$xmlName", $jsonFile, $optionalFields, $feed['type'], $filters);

    if (!$parseResult['success']) {
        continue;
    }

    $uploadResult = uploadFiletoS3($s3Client, $s3BucketName, $s3BucketPath . "$feedID.json", $jsonFile);

    if (!$uploadResult['success'] || empty($uploadResult['fileURL'])) {
        continue;
    }

    $availableFeeds[] = [
        'name' => $feed['name'],
        'url' => $uploadResult['fileURL']
    ];
    
}

//Create Available Feeds JSON File

$feedsJSON = json_encode($availableFeeds);

if (!$feedsJSON) {
    return;
}

$writeFileResult = file_put_contents("output/feeds.json", $feedsJSON);

if ($writeFileResult === false) {
    return;
}

$result = uploadFiletoS3($s3Client, $s3BucketName, $s3BucketPath . "feeds.json", "output/feeds.json");

function uploadFiletoS3($s3Client, $s3BucketName, $s3ObjectKey, $sourceFile)
{

    $uploadResult = [
        "success" => false, 
        "fileURL" => false
    ];

    $result = [];
     // Upload to AWS s3 bucket
    try {
        // Upload the file to S3 bucket
        $result = $s3Client->putObject([
           'Bucket' => $s3BucketName,
           'Key' => $s3ObjectKey,
           'ACL' => 'public-read',
           'SourceFile' => '/' . $sourceFile
        ]);

        $uploadResult['success'] = true;

        $uploadedFileName = basename('/' . $sourceFile);
        echo "File '$uploadedFileName' uploaded to S3 successfully." . PHP_EOL;
    } catch (AwsException $e) {
        echo 'Error: ' . $e->getMessage() . PHP_EOL;
    }

    if(!empty($result) && array_key_exists('effectiveUri', $result['@metadata'])){
        
        $uploadResult['fileURL'] = $result['@metadata']['effectiveUri'];      
    }

    return $uploadResult;
}
