import React from 'react';
import { Box, Text } from 'ink';
import { useStore } from '../store.js';

const Header = () => {
    const { user, currentPath, loading, error } = useStore();

    return (
        <Box flexDirection="column" borderStyle="single" borderColor="blue" paddingX={1}>
            <Box justifyContent="space-between">
                <Text>User: <Text color="green">{user?.username}</Text>@{user?.share}</Text>
                <Text>{loading ? 'Loading...' : 'Idle'}</Text>
            </Box>
            <Box>
                <Text>Path: <Text color="yellow">{currentPath || '/'}</Text></Text>
            </Box>
            {error && <Text color="red">Error: {error}</Text>}
        </Box>
    );
};

export default Header;
