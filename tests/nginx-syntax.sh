#!/bin/bash
##
# Check that nginx configs work with (nginx -t) before commiting
##
git diff --cached --name-only | while read FILE; do
if [[ "$FILE" =~ ^.+(conf)$ ]]; then #TODO: this should only check files in nginx folder
  if [[ -f $FILE ]]; then
    wp-restart-nginx 1> /dev/null
    if [ $? -ne 0 ]; then
        echo -e "\e[1;31m\tAborting commit due to errors in nginx config" >&2
        exit 1
    fi
  fi
fi
done