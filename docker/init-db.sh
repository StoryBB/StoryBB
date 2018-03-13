#!/bin/bash
su - mysql -c '/usr/bin/mysqld_safe --defaults-file=/etc/mysql/conf.d/mysql-storybb.cnf --log-error-verbosity' &

until mysql -u root -e "show databases" &> /dev/null
do
  #echo 'DEBUG: permissions check'
  #ls -ld /var/run/mysqld/
  #ls -la /var/lib/mysql
  #echo '--------------'
  echo 'DEBUG: Error log:'
  cat /var/log/mysql/mysql_err.log
  echo '--------------'
  echo "Waiting for database connection..."
  # wait for 5 seconds before check again
  sleep 5
done
echo "Starting data import"
mysql -u root < /data/StoryBB/install.sql
echo "Done!"