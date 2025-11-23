import React, { useState } from 'react';
import { StyleSheet, View, TextInput, Button, Text, ActivityIndicator, Alert, ScrollView } from 'react-native';
import { login } from '../api';
import * as SecureStore from 'expo-secure-store';

export default function Login({ onLogin }) {
  const [formData, setFormData] = useState({
    share: '',
    domain: '',
    username: '',
    password: ''
  });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleChange = (name, value) => {
    setFormData({ ...formData, [name]: value });
  };

  const handleSubmit = async () => {
    setError('');
    setLoading(true);
    try {
      const response = await login(formData);
      await SecureStore.setItemAsync('smb_token', response.data.token);
      onLogin();
    } catch (err) {
      const msg = err.response?.data?.error || 'Login failed';
      setError(msg);
      Alert.alert('Login Error', msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <View style={styles.card}>
        <Text style={styles.title}>SMB Login</Text>
        {error ? <Text style={styles.error}>{error}</Text> : null}

        <View style={styles.formGroup}>
          <Text style={styles.label}>Share Path (e.g. \\server\share)</Text>
          <TextInput
            style={styles.input}
            value={formData.share}
            onChangeText={(text) => handleChange('share', text)}
            placeholder="\\\\server\\share"
            autoCapitalize="none"
          />
        </View>

        <View style={styles.formGroup}>
          <Text style={styles.label}>Domain</Text>
          <TextInput
            style={styles.input}
            value={formData.domain}
            onChangeText={(text) => handleChange('domain', text)}
            placeholder="WORKGROUP"
            autoCapitalize="none"
          />
        </View>

        <View style={styles.formGroup}>
          <Text style={styles.label}>Username</Text>
          <TextInput
            style={styles.input}
            value={formData.username}
            onChangeText={(text) => handleChange('username', text)}
            autoCapitalize="none"
          />
        </View>

        <View style={styles.formGroup}>
          <Text style={styles.label}>Password</Text>
          <TextInput
            style={styles.input}
            value={formData.password}
            onChangeText={(text) => handleChange('password', text)}
            secureTextEntry
          />
        </View>

        <View style={styles.buttonContainer}>
          {loading ? (
            <ActivityIndicator size="small" color="#0000ff" />
          ) : (
            <Button title="Connect" onPress={handleSubmit} />
          )}
        </View>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flexGrow: 1,
    backgroundColor: '#f5f5f5',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20,
  },
  card: {
    backgroundColor: 'white',
    padding: 20,
    borderRadius: 8,
    width: '100%',
    maxWidth: 400,
    elevation: 2, // Android shadow
    shadowColor: '#000', // iOS shadow
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    marginBottom: 20,
    textAlign: 'center',
  },
  error: {
    color: 'red',
    marginBottom: 10,
    textAlign: 'center',
  },
  formGroup: {
    marginBottom: 15,
  },
  label: {
    marginBottom: 5,
    fontWeight: 'bold',
  },
  input: {
    borderWidth: 1,
    borderColor: '#ddd',
    padding: 10,
    borderRadius: 4,
    backgroundColor: '#fff',
  },
  buttonContainer: {
    marginTop: 10,
  },
});
