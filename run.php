<?php

// Import AWS SDK for PHP (autoload the AWS SDK classes)
require 'vendor/autoload.php';
require 'inc/OleeoFeedParser.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

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
    ]
 ];

 if(count($feeds) == 0){
    return;
 }

 // AWS S3 Bucket Name
$s3BucketName = getenv('S3_UPLOADS_BUCKET');

// AWS S3 Bucket Path
$s3BucketPath = '';  // Set to an empty string if the root of the bucket

// AWS S3 Region
$awsRegion = 'eu-west-2';

// Create an S3Client with the AWS credentials automatically provided by IAM instance profile
$s3Client = new S3Client([
    'region' => $awsRegion,
    'version' => 'latest'
]);


 foreach ($feeds as $feed){

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
        'addresses',
        'cities',
        'regions',
        'roleTypes',
        'contractTypes'
    ];


    $jsonFile = "output/$feedID.json";

    $result = $feed_parser->parseToJSON("output/$xmlName", $jsonFile, $optionalFields, $feed['type']);

    if(!$result['success']){
        return;
    }

    // AWS S3 Object Key
    $s3ObjectKey = $s3BucketPath . "$feedID.json";

    // Upload to AWS s3 bucket
    try {
        // Upload the file to S3 bucket
        $result = $s3Client->putObject([
            'Bucket' => $s3BucketName,
            'Key' => $s3ObjectKey,
            'ACL' => 'public-read',
            'SourceFile' => '/' . $jsonFile
        ]);

        $uploadedFileName = basename('/' . $jsonFile);
        echo "File '$uploadedFileName' uploaded to S3 successfully." . PHP_EOL;
    } catch (AwsException $e) {
        echo 'Error: ' . $e->getMessage() . PHP_EOL;
    }
 }



