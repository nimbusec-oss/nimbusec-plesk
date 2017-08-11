#!/bin/bash

fetched=$(docker ps --no-trunc --quiet --filter "ancestor=plesk/plesk")
readarray -t instances <<< "$fetched"

if [[ ${#instances[@]} == 2 ]]; then
	>&2 printf "multiple plesk instances running. abort... \n"
    exit 1
fi

current=${instances[0]}

# libraries
docker cp ../src/plib/library/  $current:/opt/psa/admin/plib/modules/nimbusec-agent-integration/

# controller
docker cp ../src/plib/controllers/  $current:/opt/psa/admin/plib/modules/nimbusec-agent-integration/

# views
docker cp ../src/plib/views/scripts/ $current:/opt/psa/admin/plib/modules/nimbusec-agent-integration/views/
