```
  ______            _   _____
 |  ____|          | | |  __ \
 | |__ ___  ___  __| | | |__) |_ _ _ __ ___  ___ _ __
 |  __/ _ \/ _ \/ _` | |  ___/ _` | '__/ __|/ _ \ '__|
 | | |  __/  __/ (_| | | |  | (_| | |  \__ \  __/ |
 |_|  \___|\___|\__,_| |_|   \__,_|_|  |___/\___|_|

```
[![Feed Parser Deployment](https://github.com/ministryofjustice/feed-parser/actions/workflows/cd.yaml/badge.svg)](https://github.com/ministryofjustice/feed-parser/actions/workflows/cd.yaml)

# FeedParser
FeedParser is a PHP application designed to run as a microservice in a kubernetes cluster. It imports an XML feed(s) and parses them into JSON format, uploading the resulting JSON files into an Amazon S3 bucket, in a folder `/feed-parser` where the file(s) can be hosted as a URL. 

The hosted s3 JSON file can then be used by WordPress, using a plugin called [Feed Importer](https://github.com/ministryofjustice/feed-importer). This plugin converts the JSON data into data used by the WordPress database.

![Feed Parser architectural overview](https://cloud-platform-e218f50a4812967ba1215eaecede923f.s3.amazonaws.com/uploads/2023/09/feed-parser-architecture-overview-1.png)

## Required
- Access to the k8s namespace you want to run the parser in

## Update this image

Raise a PR to merge new code into the `main` branch. This triggers a GitAction that pushes an image of the FeedParser to all `Hale Platform` environment namespaces, `prod`, `staging`, `dev` and `demo`. Each environment has its own ECR repository, ie, `jotw-content-devs/hale-platform-dev-feed-parser-ecr`.

### Troubleshooting - check new ECR image is in the repository
To check if the image has been pushed into the ECR repo first shell into the
service module. First make sure your terminal is in the right namespace. If it
is not, run `worm switch <env>`. Then run `kubectrl get all` and look for the
service pod. The name should look something like
`pod/cloud-platform-d2fcc98e23c3e68c-service-pod-58488bb5d7-w22sw`. Once found
copy the whole name and run `kubectrl exec -it
pod/cloud-platform-d2fcc98e23c3e68c-service-pod-58488bb5d7-w22sw -- bin/sh`
(swap your pod name in). Then you can run `aws ecr list-images
--repository-name jotw-content-devs/hale-platform-dev-feed-parser-ecr`. The one
tagged `latest` will be the one used.

## Usage

We are currently running this application in [kubernetes using a cron manifest file](https://github.com/ministryofjustice/hale-platform/blob/main/helm_deploy/wordpress/templates/cron-feedparser.yaml) that periodically runs depending on the cron schedule.

Everytime it runs, it will produce a JSON file(s) for each feed it is parsing,
plus a tracker JSON called `feeds.json`, and upload these to the namespace's s3
bucket in a folder called `/feed-parser`.

## Local development

To run locally:

1. In this repo root directory run `make build`.
2. Run `make run`

To stop run `make down` .

Files will be exported to the `/output` folder rather then exported to s3.
