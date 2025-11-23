import { StatusBar } from 'expo-status-bar';
import React, { useState, useEffect } from 'react';
import { StyleSheet, View, ActivityIndicator } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import Login from './components/Login';
import FileManager from './components/FileManager';

export default function App() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    checkLogin();
  }, []);

  const checkLogin = async () => {
    try {
      const token = await SecureStore.getItemAsync('smb_token');
      if (token) {
        setIsAuthenticated(true);
      }
    } catch (e) {
      console.warn('Error checking token', e);
    } finally {
      setLoading(false);
    }
  };

  const handleLogin = () => {
    setIsAuthenticated(true);
  };

  const handleLogout = async () => {
    try {
      await SecureStore.deleteItemAsync('smb_token');
    } catch (e) {
      console.warn('Error deleting token', e);
    }
    setIsAuthenticated(false);
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {isAuthenticated ? (
        <FileManager onLogout={handleLogout} />
      ) : (
        <Login onLogin={handleLogin} />
      )}
      <StatusBar style="auto" />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
});
