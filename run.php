<?php

// Import AWS SDK for PHP (autoload the AWS SDK classes)
require 'vendor/autoload.php';
require 'inc/OleeoFeedParser.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Check environment
$envType = getenv('ENV_TYPE');

if ($envType === 'local') {
    echo 'The environment type is local export files locally.';

    $uploadResult = [];
}

if ($envType != 'local') {
    echo 'The environment type is not local, setup and push to s3.';

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
}

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
                'fieldName' => 'businessGroup',
                'acceptedValues' => [
                    "Her Majesty's Prison and Probation Service",
                    "His Majesty's Prison and Probation Service"
                ]
            ]
        ]
    ],
 ];

if (count($feeds) == 0) {
    exit;
}

$availableFeeds = [];

foreach ($feeds as $feed) {
    $feedID = $feed['id'];
    $feedURL = $feed['url'];
    $xmlName = "$feedID.xml";
    $xmlFile = "output/$xmlName";
    $jsonFile = "output/$feedID.json";

    // Fetch the XML feed using wget
    exec("wget -O  $xmlFile $feedURL");

    // Get parser
    $feed_parser = new OleeoFeedParser();

    $optionalFields = [
       'id',
       'closingDate',
       'salary',
       'availablePositions',
       'organisation',
       'businessGroup',
       'addresses',
       'cities',
       'regions',
       'roleTypes',
       'contractTypes'
    ];

    $filters = [];

    if (array_key_exists('filters', $feed) && !empty($feed['filters'])) {
        $filters = $feed['filters'];
    }

    $parseResult = $feed_parser->parseToJSON($xmlFile, $jsonFile, $optionalFields, $feed['type'], $filters);

    if (!$parseResult['success']) {
        continue;
    }

    if ($envType === 'local') {
        $uploadResult['fileURL'] = $jsonFile;
    }

    // Export locally
    if ($envType === 'local') {
        $file_path = "output/$feedID.json";

        // Use file_put_contents to save the content to the local file
        $result = file_put_contents($file_path, file_get_contents($file_path));

        if ($result !== false) {
            echo "Feed data successfully written to the file. $result bytes written.";
        } else {
            echo "Error writing data to local file.";
        }
    }

    // Export to s3
    if ($envType !== 'local') {
        $uploadResult = uploadFiletoS3($s3Client, $s3BucketName, $s3BucketPath . "$feedID.json", $jsonFile);

        if (!$uploadResult['success'] || empty($uploadResult['fileURL'])) {
            continue;
        }
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

if ($envType == 'local') {
    exit;
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

    if (!empty($result) && array_key_exists('effectiveUri', $result['@metadata'])) {
        $uploadResult['fileURL'] = $result['@metadata']['effectiveUri'];
    }

    return $uploadResult;
}
