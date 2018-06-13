#!/bin/bash

# Run all the scripts.
if find . -name "*.php" ! -path "./vendor/*" -exec php -l {} 2>&1 \; | grep "syntax error, unexpected"; then exit 1; fi

if find . -name "*.php" ! -path "./vendor/*" -exec php other/buildTools/check-smf-license.php {} 2>&1 \; | grep "Error:"; then exit 1; fi
