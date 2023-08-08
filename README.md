```
  ______            _   _____                         
 |  ____|          | | |  __ \                        
 | |__ ___  ___  __| | | |__) |_ _ _ __ ___  ___ _ __ 
 |  __/ _ \/ _ \/ _` | |  ___/ _` | '__/ __|/ _ \ '__|
 | | |  __/  __/ (_| | | |  | (_| | |  \__ \  __/ |   
 |_|  \___|\___|\__,_| |_|   \__,_|_|  |___/\___|_|   

```                                                     
# FeedParser
Microservice that takes the external Oleeo feed, cleans the XML data to our specifications, converts that to json and then uploads it to our namespace's associated s3 bucket.

# Deployment

Merging or pushing to the `main` branch triggers a GitAction to push this FeedParser image to our `Hale Platform` dev namespace. It has it's own ECR repository there called `jotw-content-devs/hale-platform-dev-feed-parser-ecr`.
