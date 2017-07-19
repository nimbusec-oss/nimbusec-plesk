#!/bin/bash

extension_name="nimbusec-agent-integration"

root=$1
destination=$2

# check root of project
if [[ ! $root ]]; then
    root=$(pwd)
fi
root=$(readlink -f $root)

if [[ ! -d $root/src ]]; then
    >&2 printf "$root is not a valid plesk extension project\n"
    exit 1
fi

# check destination
if [[ ! $destination ]]; then
    destination="$HOME/tmp"
fi
destination=$(readlink -f $destination)

if [[ ! -d $destination ]]; then
    >&2 printf "$destination is not a valid directory\n"
    exit 1
fi

# start zipping
cd $root/src
zip -rq $destination/$extension_name.zip ./
cd - > /dev/null

printf "$destination/$extension_name.zip"
