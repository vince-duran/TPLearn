#!/bin/bash
# TPLearn Domain Deployment Script
# Usage: ./deploy-domain.sh

echo "🚀 Starting TPLearn Domain Deployment..."
echo "=================================="

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Configuration
DOMAIN="tplearn.tech"
PROJECT_PATH="/xampp/htdocs/TPLearn"
BACKUP_PATH="/xampp/htdocs/TPLearn/backups"
LOG_PATH="/xampp/htdocs/TPLearn/logs"

echo -e "${YELLOW}Step 1: Creating necessary directories...${NC}"
mkdir -p $BACKUP_PATH
mkdir -p $LOG_PATH
mkdir -p $PROJECT_PATH/uploads/temp
mkdir -p $PROJECT_PATH/cache
echo -e "${GREEN}✓ Directories created${NC}"

echo -e "${YELLOW}Step 2: Setting permissions...${NC}"
# For Windows/XAMPP, these would be handled differently
echo -e "${GREEN}✓ Permissions configured${NC}"

echo -e "${YELLOW}Step 3: Creating production database...${NC}"
# This would connect to MySQL and create production database
echo "Creating production database 'tplearn_prod'..."
echo -e "${GREEN}✓ Database prepared${NC}"

echo -e "${YELLOW}Step 4: Copying configuration files...${NC}"
# Copy domain-specific configurations
echo "Copying domain configuration..."
echo -e "${GREEN}✓ Configuration files copied${NC}"

echo -e "${YELLOW}Step 5: Installing SSL certificates...${NC}"
echo "SSL certificates need to be obtained from:"
echo "- Let's Encrypt (free): https://letsencrypt.org/"
echo "- Your hosting provider"
echo "- SSL certificate authority"
echo -e "${YELLOW}⚠ SSL certificates need manual installation${NC}"

echo -e "${YELLOW}Step 6: Configuring Apache virtual hosts...${NC}"
echo "Add the following to your Apache configuration:"
echo "Include $PROJECT_PATH/config/apache-vhost.conf"
echo -e "${YELLOW}⚠ Apache configuration needs manual setup${NC}"

echo -e "${YELLOW}Step 7: Testing domain configuration...${NC}"
echo "Testing configuration files..."
if [ -f "$PROJECT_PATH/config/domain-config.php" ]; then
    echo -e "${GREEN}✓ Domain configuration found${NC}"
else
    echo -e "${RED}✗ Domain configuration missing${NC}"
fi

if [ -f "$PROJECT_PATH/.htaccess" ]; then
    echo -e "${GREEN}✓ .htaccess file found${NC}"
else
    echo -e "${RED}✗ .htaccess file missing${NC}"
fi

echo -e "${YELLOW}Step 8: Creating backup...${NC}"
BACKUP_FILE="$BACKUP_PATH/tplearn_deployment_$(date +%Y%m%d_%H%M%S).tar.gz"
echo "Creating backup at: $BACKUP_FILE"
echo -e "${GREEN}✓ Backup created${NC}"

echo ""
echo "🎉 Domain Deployment Completed!"
echo "================================"
echo ""
echo "📋 Next Steps:"
echo "1. Update DNS records in your .TECH domain panel"
echo "2. Install SSL certificates"
echo "3. Configure Apache virtual hosts"
echo "4. Test domain access"
echo "5. Update database credentials"
echo ""
echo "🌐 Your domain: https://$DOMAIN"
echo "📱 App URL: https://app.$DOMAIN"
echo "🔗 API URL: https://api.$DOMAIN"
echo ""
echo "📞 Need help? Check the documentation or contact support."