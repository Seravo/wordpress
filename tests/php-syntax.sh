#!/bin/bash
##
# Check syntax for all *.php and *.module files
##
git diff --cached --name-only | while read FILE; do
if [[ "$FILE" =~ ^.+(php|module)$ ]]; then
    if [[ -f $FILE ]]; then
        php -l "$FILE" 1> /dev/null
        if [ $? -ne 0 ]; then
            echo -e "\e[1;31m\tAborting commit due to files with syntax errors" >&2
            exit 1
        fi
    fi
fi
done