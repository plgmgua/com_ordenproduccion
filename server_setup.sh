#!/bin/bash
set -e

USERNAME="pgrant"
USER_HOME="/home/$USERNAME"
SSH_DIR="$USER_HOME/.ssh"
AUTH_KEYS="$SSH_DIR/authorized_keys"

echo "Updating packages..."
sudo apt update

echo "Installing openssh-server..."
sudo apt install -y openssh-server

echo "Enabling and starting ssh service..."
sudo systemctl enable --now ssh

echo "Creating .ssh folder if needed..."
sudo mkdir -p "$SSH_DIR"

echo "Creating authorized_keys if needed..."
sudo touch "$AUTH_KEYS"

echo "Fixing ownership and permissions..."
sudo chown -R "$USERNAME:$USERNAME" "$SSH_DIR"
sudo chmod 700 "$SSH_DIR"
sudo chmod 600 "$AUTH_KEYS"

echo "Ensuring sshd allows public key auth..."
sudo cp /etc/ssh/sshd_config /etc/ssh/sshd_config.bak.$(date +%F-%H%M%S)

sudo sed -i 's/^#\?PubkeyAuthentication.*/PubkeyAuthentication yes/' /etc/ssh/sshd_config

if grep -q '^#\?AuthorizedKeysFile' /etc/ssh/sshd_config; then
    sudo sed -i 's|^#\?AuthorizedKeysFile.*|AuthorizedKeysFile .ssh/authorized_keys|' /etc/ssh/sshd_config
else
    echo 'AuthorizedKeysFile .ssh/authorized_keys' | sudo tee -a /etc/ssh/sshd_config > /dev/null
fi

echo "Restarting ssh..."
sudo systemctl restart ssh

echo
echo "==================== STATUS ===================="
sudo systemctl --no-pager status ssh | sed -n '1,12p'
echo
echo "Listening on port 22:"
ss -tlnp | grep ':22' || true
echo
echo "authorized_keys file:"
ls -l "$AUTH_KEYS"
echo
echo "IMPORTANT:"
echo "Now paste the CLIENT public key into:"
echo "  $AUTH_KEYS"
echo
echo "Example:"
echo "  nano $AUTH_KEYS"
echo
echo "Then test locally from the server with:"
echo "  ssh $USERNAME@localhost"
