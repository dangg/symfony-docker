# Symfony with Docker - a slim-fit tutorial
[Medium Article](https://medium.com/@dan_gurgui/symfony-with-docker-slim-fit-tutorial-bb9cfb121728)

## Intro

In my experience of over 10 years as an Engineer, I have never encountered a piece of technology with a bigger impact than Docker. It has increased my productivity, helped me debug and fix bugs faster and deploy almost instantly. I remember the days when I used to replicate the production environment by using a separate repository of scripts, by making sure that the versions of all libraries and servers aligned and that my development environment mirrors production perfectly. This used to consume many hours of development time and a lot of mental energy dedicated to little details. Ever since I started using Docker 3 years ago, I have saved invaluable mental energy and hundreds of hours for setting up environments, deploying and maintaining applications. I have written a very pragmatic tutorial, focused on how to reap the benefits of Docker as fast as possible. In this tutorial, I will focus mainly on deploying a slim Symfony app for development.

	
## Step 1 - Installing Docker

Docker runs on any platform, however, I recommend that you run it inside an Ubuntu virtual machine for macOS or Windows. These platforms don’t support Docker virtualization very well and you might run into issues hard to debug. I’m running Docker inside an Ubuntu Server machine inside VirtualBox on my Windows laptop and it operates super smoothly. I allocated 2 GB of RAM to the machine and 4 CPUs. Below you can find the instructions for installing VirtualBox, Ubuntu, and Docker

- Official Docker documentation for installing https://docs.docker.com/v17.09/engine/installation/
- Virtual Box https://www.virtualbox.org/
- Ubuntu Server https://ubuntu.com/download/server
- Additional resource for install ubuntu server on virtualbox https://hibbard.eu/install-ubuntu-virtual-box/


## Step 2 - The Dockerfile

To create a Docker container, Docker reads the instructions from the Dockerfile. This file is the most popular way of building docker containers and is very easy to understand and use. Let’s build a container from the alpine image, a stripped-down Linux distribution. Create a Dockerfile with the following contents

    FROM alpine
    RUN echo "Hello World!"

and run

    sudo docker  build -t simple-alpine .
    sudo docker image ls

You should see:

    REPOSITORY          TAG                 IMAGE ID            CREATED             SIZE
    simple-alpine       latest              5906f9bc045e        2 seconds ago       5.59MB
    
Docker read the Dockerfile and saw that it needs to create a new image based on the alpine image. The image was not available locally so it downloaded it from hub.docker.com After this it created the image and tagged it with the name “simple-alpine” which can be used to reference it. Now if you run

    sudo docker run -it simple-alpine /bin/sh
    
You will “connect” to the container and you will be able to play around. Notice that the container is fully connected to the internet and we have full access to this Linux instance without the need to install it. Docker does all the magic

## Step 3 - The docker-compose

Besides the Dockerfile, there is another way you can run containers, and that is by using a docker-compose YAML file. In this file, you can define multiple containers with different images and you can configure how these containers interact with the host machine and between themselves. For each image, Docker will run a container that will read the Dockerfile and execute the instructions in that file. Install docker-compose by running

    
    sudo apt-get install docker-compose
    
And then add this to docker-compose.yml file

    version: "3"
    
    services:
      webapp:
        container_name: webapp
        image: "nginx:latest" 
        ports:
          - "50080:80"

This tells docker to use version 3 of docker-compose. Using this version, we define the service called webapp that will 
run a container based on the official image of nginx. This container will have the port 80 exposed as port 50080. Let's run

    sudo docker-compose up -d
    
We should see docker pulling the nginx image and running a container based on this image. Next, when we navigate to the 
port exposed by docker we will see the nginx welcome message informing us of a successful installation! In my case it's
http://192.168.88.225:50080/ (the IP address of the VM running ubuntu, running docker). In your case it might be
http://127.0.0.1:50080/

Now let's add a PHP-FPM service that will complement our nginx. docker-compose.yml:

    version: "3"
    
    services:
      webapp:
        container_name: webapp
        image: "nginx:latest" 
        ports:
          - "50080:80"
      php-fpm:
        container_name: php_fpm
        image: akeneo/fpm
        user: docker
        ports:
          - "59000:9000"
          
I’m using an image I’ve discovered while browsing https://hub.docker.com There are plenty of images made public there with good documentation and many things pre-configured. I’ve chosen this one because it has PHP 7.3 with many extensions, it’s well maintained and it doesn’t have too much of what you don’t need.

    sudo docker-compose down
    sudo docker-compose up -d

Next, let's see how we can change the configuration of nginx to suit our needs. You can "connect" to the containers 
and see the file structure, browse around, test things, etc

    sudo docker exec -it webapp bash
    sudo docker exec -it php_fpm bash

Create config/nginx/nginx.conf and add this to it

    server {
        listen       80;
        server_name  localhost;
    
        location / {
            # try to serve file directly, fallback to index.php and let Symfony handle it
            try_files $uri /index.php$is_args$args;
        }
    
        # pass the PHP scripts to FastCGI docker image
        location ~ ^/index\.php(/|$) {
            root           html;
            fastcgi_pass   php-fpm:9001;
            fastcgi_index  index.php;
            # this is the absolute path on the docker Image
            fastcgi_param  SCRIPT_FILENAME  /home/docker/public$fastcgi_script_name;
            include        fastcgi_params;
        }
    }

Also change docker-compose.yml to this

    version: "3"
      
    services:
      webapp:
        volumes:
          - ./config/nginx:/etc/nginx/conf.d
        container_name: webapp
        image: "nginx:latest" # official image
        ports:
          - "50080:80"
      php-fpm:
        volumes:
          - ./:/home/docker # mounting our application on a specific path
        container_name: php_fpm
        image: akeneo/fpm
        user: docker
        ports:
          - "59000:9000"
    
The volumes section tells docker to mount the local folder to the remote folder. The mounting will override anything that is in that folder. This is a very easy way of connecting the local files to the container. Also, any changes made by the container will be reflected locally. We can optionally add the container’s logging locally with this volume mount

          - ./var/log:/var/log/nginx

So now, after restarting our docker-compose and we navigate to our service URL we should see the message
       
    File not found.      
     
This is normal and it’s being thrown at us by php-fpm which doesn’t have an index.php file to interpret. So we should begin preparing our Symfony app. Our php-fpm image has composer installed on it and we can use this command to create a skeleton for our app. After running the command below we will have the folder with Symfony on both our container and local image


    sudo docker exec -it php_fpm composer create-project symfony/skeleton symfony-app
    
! Warning ! - In case composer fails to install the symfony app, run the command on the container in an unmounted volum
    
Now the last step left is to map the folder structure in our docker containers. We have to map:
* The public folder of the symfony app in our nginx folder
* The symfony app in our php-fpm container


    version: "3"
      
    services:
      webapp:
        volumes:
          - ./config/nginx:/etc/nginx/conf.d
          - ./symfony-app/public:/usr/share/nginx/html
        container_name: webapp
        image: "nginx:latest" # official image
        ports:
          - "50080:80"
      php-fpm:
        volumes:
          - ./symfony-app:/home/docker # mounting our application on a specific path
        container_name: php_fpm
        image: akeneo/fpm
        user: docker
        ports:
          - "59000:9000"
          
 And that's it! Navigate to your machine's 50080 port and you should see the symfony installation message!
 
 ## Final steps
 
Now that we have the docker-compose ready it’s just a matter of tinkering around with configurations, committing and deploying on our production environment. Deployment can be done manually, but it’s highly recommended to use Kubernetes and integrate everything in a CI/CD pipeline.

For local development, you can start altering the files inside your symfony-app folder and test the changes on the webapp deployment. I’m using Symfony just for microservices nowadays, but you can use this setup for web applications and you can have other services integrated using the same docker-compose file (redis, mysql, nodejs deploys, etc)

Docker makes it easy to work as a team, to deploy apps while easily managing the service dependencies and configurations. It’s here to stay and its evolution will be influenced by how the container-orchestration tools leverage it. Kubernetes, Prometheus, Mesos, Flocker are just some of the tools that one needs to master after Docker.



 ![image](https://pics.me.me/it-works-on-my-machine-then-well-ship-your-machine-62072263.png)