import React, { useState } from 'react';
import { login } from '../api';

function Login({ onLogin }) {
  const [formData, setFormData] = useState({
    share: '',
    domain: '',
    username: '',
    password: ''
  });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      const response = await login(formData);
      localStorage.setItem('smb_token', response.data.token);
      onLogin();
    } catch (err) {
      setError(err.response?.data?.error || 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="container">
      <div className="card">
        <h2>SMB Login</h2>
        {error && <p style={{ color: 'red' }}>{error}</p>}
        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label>Share Path (e.g. \\server\share)</label>
            <input
              name="share"
              value={formData.share}
              onChange={handleChange}
              required
              placeholder="\\\\server\\share"
            />
          </div>
          <div className="form-group">
            <label>Domain</label>
            <input
              name="domain"
              value={formData.domain}
              onChange={handleChange}
              placeholder="WORKGROUP"
            />
          </div>
          <div className="form-group">
            <label>Username</label>
            <input
              name="username"
              value={formData.username}
              onChange={handleChange}
              required
            />
          </div>
          <div className="form-group">
            <label>Password</label>
            <input
              type="password"
              name="password"
              value={formData.password}
              onChange={handleChange}
              required
            />
          </div>
          <button type="submit" disabled={loading}>
            {loading ? 'Connecting...' : 'Connect'}
          </button>
        </form>
      </div>
    </div>
  );
}

export default Login;
