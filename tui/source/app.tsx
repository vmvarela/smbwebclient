import React, { useState } from 'react';
import { render } from 'ink';
import Login from './components/Login.js';
import FileExplorer from './components/FileExplorer.js';

const App = () => {
    const [isLoggedIn, setIsLoggedIn] = useState(false);
    const [username, setUsername] = useState('');

    const handleLogin = (user: string) => {
        setUsername(user);
        setIsLoggedIn(true);
    };

    const handleLogout = () => {
        setIsLoggedIn(false);
        setUsername('');
    };

    return (
        <>
            {!isLoggedIn ? (
                <Login onLogin={handleLogin} />
            ) : (
                <FileExplorer onLogout={handleLogout} />
            )}
        </>
    );
};

render(<App />);
