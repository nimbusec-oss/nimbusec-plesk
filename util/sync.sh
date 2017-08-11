#!/bin/bash

current=$(docker ps --format "{{.ID}}")

# libraries
docker cp ../src/plib/library/  $current:/opt/psa/admin/plib/modules/nimbusec-agent-integration/

# controller
docker cp ../src/plib/controllers/  $current:/opt/psa/admin/plib/modules/nimbusec-agent-integration/

# views
docker cp ../src/plib/views/scripts/ $current:/opt/psa/admin/plib/modules/nimbusec-agent-integration/views/
