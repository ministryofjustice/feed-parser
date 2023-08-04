<?php
// Fetch the XML feed using wget
exec('wget -O /output/input.xml https://justicejobs.tal.net/vx/mobile-0/appcentre-1/brand-2/candidate/jobboard/vacancy/3/feed/structured');

// Import AWS SDK for PHP (autoload the AWS SDK classes)
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Function to convert XML to JSON
function xmlToJson($xmlString)
{
    $xml = simplexml_load_string($xmlString);
    $json = json_encode($xml);
    return $json;
}

// AWS S3 Bucket Name
$s3BucketName = getenv('S3_UPLOADS_BUCKET');

// AWS S3 Bucket Path (if needed)
$s3BucketPath = '';  // Set to an empty string if the root of the bucket

// AWS S3 Object Key
$s3ObjectKey = $s3BucketPath . 'output.json';

// AWS S3 Region (replace 'your-region' with your actual AWS region)
$awsRegion = 'eu-west-2';

// Create an S3Client with the AWS credentials automatically provided by IAM instance profile
$s3Client = new S3Client([
    'region' => $awsRegion,
    'version' => 'latest'
]);

// Read the XML content from the file
$xmlContent = file_get_contents('/output/input.xml');

// Convert XML to JSON
$jsonData = xmlToJson($xmlContent);

// Write JSON data to a file
file_put_contents('/output/output.json', $jsonData);

try {
    // Upload the file to S3 bucket
    $result = $s3Client->putObject([
        'Bucket' => $s3BucketName,
        'Key' => $s3ObjectKey,
        'SourceFile' => '/output/output.json',
    ]);

    echo 'File uploaded to S3 successfully.' . PHP_EOL;
} catch (AwsException $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
