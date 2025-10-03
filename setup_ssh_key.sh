#!/bin/bash

# SSH Key Setup Script for com_ordenproduccion CI/CD
# This script sets up the SSH public key for GitHub Actions deployment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Public key from GitHub
PUBLIC_KEY="ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAACAQC9+VPaQjFMC4t2ixYp8coEGDgkqk6tSm/zG8hmQe7nr15DFVuDP5MEIo+RY7kEB1nSgO2bOlE7MIRYF3z5gfKmXJHodTj58h+IIQ5vCNbTLGHMvlSK0RSf8xSNGXYl317idZKz+hl+iO/sOE4v4wJ6rJmk2b0oxZLglLGQT0Aw5N7YBE7tH1OTwxt7bZ72QBm40KLncBzjKVXmvNdtuUEOH5H7Omay4+DsV1VIc+c5LI+jNWrm5yWaF1OvqZQBM3zBuc34/gjYi1R7f9rYlVLzvKvpeJS6UbsFC5bGBx2UmQJPnwmCnrC43rNM0WzDT+h2di7xHkgiQQKplGutFMyz9c/Y9VDAldLXvoObZ45cKAf8hlFCLz3F+yUxzHndC0if3EUa6ZcgYdzuOViVuuePjQboD7aL5OAqgig8jS2WkDQjKD6CcNX4woAT5JSj0oLPwGBAF4xTZvWL2hAEOJetmHln94QAevLeKnKMdXTwyh/Eq/mhuSv9miIT2q0uFQ3miYXR507t6cgoDj/6qaz1Xh//azAxxIZSGP8yZK5EGlQxE7vlwInbdFRK4svI/xTodpfa9EyuYDHN7oaTshLl4DKboHkI383kE6NHMwILRda+Z40JP01sVli5FtqbL02i7fV3++h/YYKHdJIf4PLrK8sdKC4JdADvBIYP8rmow== plgmgua@gmail.com"

log "Setting up SSH key for GitHub Actions CI/CD deployment..."

# Create .ssh directory if it doesn't exist
log "Creating .ssh directory..."
mkdir -p ~/.ssh
chmod 700 ~/.ssh

# Check if authorized_keys file exists
if [ -f ~/.ssh/authorized_keys ]; then
    log "authorized_keys file already exists"
    
    # Check if the key is already present
    if grep -q "plgmgua@gmail.com" ~/.ssh/authorized_keys; then
        warning "SSH key for plgmgua@gmail.com already exists in authorized_keys"
        log "Removing old key and adding new one..."
        
        # Remove old key
        grep -v "plgmgua@gmail.com" ~/.ssh/authorized_keys > ~/.ssh/authorized_keys.tmp
        mv ~/.ssh/authorized_keys.tmp ~/.ssh/authorized_keys
    fi
else
    log "Creating authorized_keys file..."
    touch ~/.ssh/authorized_keys
fi

# Add the public key to authorized_keys
log "Adding public key to authorized_keys..."
echo "$PUBLIC_KEY" >> ~/.ssh/authorized_keys

# Set proper permissions
log "Setting proper permissions..."
chmod 600 ~/.ssh/authorized_keys

# Verify the key was added
if grep -q "plgmgua@gmail.com" ~/.ssh/authorized_keys; then
    success "SSH public key added successfully!"
else
    error "Failed to add SSH public key"
    exit 1
fi

# Display the authorized_keys content for verification
log "Current authorized_keys content:"
echo "----------------------------------------"
cat ~/.ssh/authorized_keys
echo "----------------------------------------"

# Test SSH configuration
log "Testing SSH configuration..."
if [ -f ~/.ssh/authorized_keys ] && [ -r ~/.ssh/authorized_keys ]; then
    success "SSH configuration looks good!"
else
    error "SSH configuration has issues"
    exit 1
fi

# Display next steps
echo ""
log "Next steps:"
echo "1. Add the private key to GitHub Secrets:"
echo "   - Go to: https://github.com/plgmgua/com_ordenproduccion/settings/secrets/actions"
echo "   - Add secret: SSH_PRIVATE_KEY"
echo "   - Value: Content of ~/.ssh/gitkeyplgmgua from your Mac"
echo ""
echo "2. Test the connection from your Mac:"
echo "   ssh -i ~/.ssh/gitkeyplgmgua pgrant@webserver"
echo ""
echo "3. Test CI/CD deployment:"
echo "   git add ."
echo "   git commit -m 'test: Test CI/CD deployment'"
echo "   git push origin main"
echo ""

success "SSH key setup completed successfully!"
success "Ready for GitHub Actions CI/CD deployment!"
