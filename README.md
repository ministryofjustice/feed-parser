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
FeedParser is a PHP application designed to run as a microservice in a kubernetes cluster. It fetches structured job feeds from various URLs, parse them into JSON format, and upload the resulting JSON files to an Amazon S3 bucket where the file can be hosted as a URL. Our WordPress plugin called feed-importer can then use this JSON URL feed to populate the data into WordPress.

## Required
- IAM instance profile setup if running on a k8s pod

## Update this image

Make required changes and merge into main. Merging or pushing to the `main` branch triggers a GitAction that pushes an image of the FeedParser to all `Hale Platform` environment namespaces, `prod`, `staging`, `dev` and `demo`. It has it's own ECR repository in each namespace called `jotw-content-devs/hale-platform-dev-feed-parser-ecr`.

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

## Local development

To run locally:

1. In this repo root directory run `make build`.
2. Run `make run`

To stop run `make down` .

Files will be exported to the `/output` folder rather then exported to s3.
