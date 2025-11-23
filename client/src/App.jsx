import React, { useState, useEffect } from 'react';
import Login from './components/Login';
import FileManager from './components/FileManager';

function App() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem('smb_token');
    if (token) {
      setIsAuthenticated(true);
    }
  }, []);

  const handleLogin = () => {
    setIsAuthenticated(true);
  };

  const handleLogout = () => {
    localStorage.removeItem('smb_token');
    setIsAuthenticated(false);
  };

  return (
    <div className="App">
      <header style={{padding: '20px', textAlign: 'center', backgroundColor: '#333', color: 'white'}}>
        <h1>SMB Web Client</h1>
      </header>

      <main>
        {isAuthenticated ? (
          <FileManager onLogout={handleLogout} />
        ) : (
          <Login onLogin={handleLogin} />
        )}
      </main>
    </div>
  );
}

export default App;
