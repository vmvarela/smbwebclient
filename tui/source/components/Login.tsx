import React, { useState } from 'react';
import { Box, Text, useInput } from 'ink';
import TextInput from 'ink-text-input';

interface LoginProps {
    onLogin: (username: string) => void;
}

const Login: React.FC<LoginProps> = ({ onLogin }) => {
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [focus, setFocus] = useState<'username' | 'password'>('username');
    const [error, setError] = useState('');

    useInput((input, key) => {
        if (key.return) {
            if (focus === 'username') {
                if (username.trim()) {
                    setFocus('password');
                }
            } else {
                if (password.trim()) {
                    onLogin(username);
                }
            }
        }
        if (key.upArrow || key.downArrow) {
             setFocus(focus === 'username' ? 'password' : 'username');
        }
    });

    return (
        <Box flexDirection="column" borderStyle="round" borderColor="cyan" padding={1}>
            <Text bold color="green">SMB Web Client TUI</Text>
            <Box marginTop={1}>
                <Text color={focus === 'username' ? 'green' : 'white'}>Username: </Text>
                <TextInput
                    value={username}
                    onChange={setUsername}
                    focus={focus === 'username'}
                />
            </Box>
            <Box>
                <Text color={focus === 'password' ? 'green' : 'white'}>Password: </Text>
                <TextInput
                    value={password}
                    onChange={setPassword}
                    focus={focus === 'password'}
                    mask="*"
                />
            </Box>
            {error ? <Box marginTop={1}><Text color="red">{error}</Text></Box> : null}
            <Box marginTop={1}>
                <Text color="gray">Press Enter to submit, Up/Down to switch fields</Text>
            </Box>
        </Box>
    );
};

export default Login;
