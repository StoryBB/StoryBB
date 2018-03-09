cd ../
cp docker/Dockerfile.mysql ./Dockerfile
docker build . -t sockdrawer/storybb:mysql-testData
docker push sockdrawer/storybb:mysql-testData
cp docker/Dockerfile.php ./Dockerfile
docker build . -t sockdrawer/storybb:latest
docker push sockdrawer/storybb:latest
