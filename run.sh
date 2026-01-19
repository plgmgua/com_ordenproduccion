#!/bin/bash

rm deploy_to_server.sh
wget https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/refs/heads/main/deploy_to_server.sh
chmod +x deploy_to_server.sh
./deploy_to_server.sh
