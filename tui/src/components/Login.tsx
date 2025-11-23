import React, { useState } from 'react';
import { Box, Text, useInput } from 'ink';
import TextInput from 'ink-text-input';
import { useStore } from '../store.js';

const Login = () => {
    const { login, loginError, loading } = useStore();
    const [field, setField] = useState(0); // 0: share, 1: domain, 2: username, 3: password
    const [share, setShare] = useState('share'); // Default based on docker-compose
    const [domain, setDomain] = useState('');
    const [username, setUsername] = useState('testuser'); // Default
    const [password, setPassword] = useState('testpassword'); // Default

    useInput((input, key) => {
        if (key.return) {
            if (field < 3) {
                setField(field + 1);
            } else {
                // Submit
                login({ share, domain, username, password });
            }
        } else if (key.upArrow) {
            setField(Math.max(0, field - 1));
        } else if (key.downArrow) {
            setField(Math.min(3, field + 1));
        }
    });

    if (loading) {
        return <Text>Logging in...</Text>;
    }

    return (
        <Box flexDirection="column" borderStyle="round" borderColor="cyan" padding={1}>
            <Text bold>SMB Login</Text>
            <Box flexDirection="column" marginTop={1}>
                <Box>
                    <Text color={field === 0 ? 'green' : 'white'}>Share: </Text>
                    <TextInput
                        value={share}
                        onChange={setShare}
                        focus={field === 0}
                    />
                </Box>
                <Box>
                    <Text color={field === 1 ? 'green' : 'white'}>Domain: </Text>
                    <TextInput
                        value={domain}
                        onChange={setDomain}
                        focus={field === 1}
                    />
                </Box>
                <Box>
                    <Text color={field === 2 ? 'green' : 'white'}>Username: </Text>
                    <TextInput
                        value={username}
                        onChange={setUsername}
                        focus={field === 2}
                    />
                </Box>
                <Box>
                    <Text color={field === 3 ? 'green' : 'white'}>Password: </Text>
                    <TextInput
                        value={password}
                        onChange={setPassword}
                        focus={field === 3}
                        mask="*"
                    />
                </Box>
            </Box>
            {loginError && <Text color="red">{loginError}</Text>}
            <Text color="gray">Press Enter to next/submit, Up/Down to navigate fields</Text>
        </Box>
    );
};

export default Login;
