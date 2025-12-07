#!/bin/bash

echo "🔍 SMB Web Client - Debug Anonymous Access"
echo "==========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if docker-compose is running
if ! docker-compose ps | grep -q "samba1"; then
    echo -e "${RED}❌ Samba containers are not running${NC}"
    echo "Run: docker-compose up -d"
    exit 1
fi

echo -e "${GREEN}✅ Services running${NC}"
echo ""

# Test 1: Guest access to samba1
echo "📋 Test 1: Guest access to samba1"
RESULT=$(docker exec smbwebclient-smbwebclient-1 smbclient -L samba1 -U guest -N 2>&1 | grep -c "SHARE")
if [ "$RESULT" -gt 0 ]; then
    echo -e "${GREEN}✅ Guest can list shares${NC}"
else
    echo -e "${RED}❌ Guest cannot list shares${NC}"
fi
echo ""

# Test 2: List shares details
echo "📋 Test 2: Available shares on samba1"
docker exec smbwebclient-smbwebclient-1 smbclient -L samba1 -U guest -N 2>&1 | grep "Disk\|IPC"
echo ""

# Test 3: Try mounting/listing SHARE1
echo "📋 Test 3: Try accessing SHARE1 as guest"
RESULT=$(docker exec smbwebclient-smbwebclient-1 smbclient //samba1/SHARE1 -U guest -N -c "ls" 2>&1 | grep -c "total")
if [ "$RESULT" -gt 0 ]; then
    echo -e "${GREEN}✅ Can access SHARE1${NC}"
else
    echo -e "${YELLOW}⚠️  May have issues accessing SHARE1${NC}"
fi
echo ""

# Test 4: Check PHP SMB connection
echo "📋 Test 4: PHP application test"
echo "Visit: http://localhost:8080"
echo "- You should NOT see a login form"
echo "- You should see SHARE1 and SHARE2 listed"
echo ""

# Show configuration
echo "📋 Current Configuration:"
echo "SMB_DEFAULT_SERVER: $(grep SMB_DEFAULT_SERVER .env | cut -d= -f2)"
echo "APP_ALLOW_ANONYMOUS: $(grep APP_ALLOW_ANONYMOUS .env | cut -d= -f2)"
echo ""
echo -e "${YELLOW}Note:${NC} If not working, check:"
echo "1. Samba config allows guest access (guest ok = yes)"
echo "2. firewall/network isolation between containers"
echo "3. PHP smbclient extension is loaded"
