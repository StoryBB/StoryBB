echo "Running developer setup. NOTE: This only supports Debian systems."

# Ensure PHP is installed and up to date
if [ php -v >/dev/null 2>&1 ]; then 
    echo "PHP already installed"
else
    sudo apt-get install -y php7.0 ||  echo "ERROR! Please install PHP 7.0 manually"
fi

# Copy files up a directory
cp ./other/install.php ./install.php
cp ./other/Settings.php ./Settings.php
cp ./other/install_3-0_mysql.sql ./install_3-0_mysql.sql
cp ./other/composerInstaller ./composerInstaller

# Create special files
touch Settings_bak.php

# Adjust file permissions
chmod -R a+rwx ./*

# install Composer
php composerInstaller || echo "ERROR: Please install Composer manually using the steps located at https://getcomposer.org/doc/00-intro.md"

#install dependencies with Composer
composer install

# set up mysql
if [ -f /etc/init.d/mysql* ]; then
    echo "MySQL already installed"
else 
    sudo apt-get install -y mysql-server || echo "ERROR! Please install mysql manually"
fi

# Give install instructions
echo "Now please visit localhost in your web browser to finish installation"
echo "Note: if  you are using c9, don't forget to hit 'run project' first"