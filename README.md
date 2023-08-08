```
  ______            _   _____                         
 |  ____|          | | |  __ \                        
 | |__ ___  ___  __| | | |__) |_ _ _ __ ___  ___ _ __ 
 |  __/ _ \/ _ \/ _` | |  ___/ _` | '__/ __|/ _ \ '__|
 | | |  __/  __/ (_| | | |  | (_| | |  \__ \  __/ |   
 |_|  \___|\___|\__,_| |_|   \__,_|_|  |___/\___|_|   

```                                                     
# Feed Parser
Microservice that takes internal/external feeds and compiles them into xml and json formats.

# Deployment

Merging or pushing to the `main` branch triggers a GitAction to push this FeedParser image to our `Hale Platform` dev namespace. It has it's own ECR repository there called `jotw-content-devs/hale-platform-dev-feed-parser-ecr`.
