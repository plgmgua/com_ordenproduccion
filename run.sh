#!/bin/bash

# Remove old versions to prevent .1, .2, etc. extensions
rm -f deploy_to_server.sh deploy_to_server.sh.* 2>/dev/null || true

# Download the script
wget https://raw.githubusercontent.com/plgmgua/com_ordenproduccion/refs/heads/main/deploy_to_server.sh

# Make it executable
chmod +x deploy_to_server.sh

# Run the deployment script
./deploy_to_server.sh
