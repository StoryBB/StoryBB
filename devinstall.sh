# Copy files up a directory
cp ./other/install.php ./install.php
cp ./other/install.php ./Settings.php
cp ./other/install_3-0_mysql.sql ./install_3-0_mysql.sql
cp ./other/composerInstaller ./composerInstaller

# Create special files
touch Settings_bak.php
touch db_last_error.php

# Adjust file permissions
chmod -R a+rwx ./*

# install Composer
php composerInstaller

#install dependencies with Composer
composer install

# set up mysql
if [ -f /etc/init.d/mysql* ]; then
    echo "MySQL already installed"
else 
    sudo apt-get install -y mysql-server
fi

# Give install instructions
echo "Now please visit localhost/install.php to finish installation"