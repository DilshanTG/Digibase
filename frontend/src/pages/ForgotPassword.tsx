import { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../lib/api';

export function ForgotPassword() {
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setIsLoading(true);

    try {
      await api.post('/forgot-password', { email });
      setSuccess('Password reset link sent to your email. Please check your inbox.');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to send reset link. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-[#1c1c1c]">
      <div className="max-w-md w-full mx-4 animate-slideUp">
        <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-lg p-8">
          <div className="text-center mb-8">
            <div className="w-12 h-12 bg-gradient-to-br from-[#3ecf8e] to-[#24b47e] rounded-lg flex items-center justify-center mx-auto mb-4">
              <span className="text-white font-bold text-xl">D</span>
            </div>
            <h1 className="text-2xl font-semibold text-[#ededed]">Reset Password</h1>
            <p className="text-[#6b6b6b] mt-2">Enter your email to recover your account</p>
          </div>

          {error && (
            <div className="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-md mb-6 animate-fadeIn">
              {error}
            </div>
          )}

          {success && (
            <div className="bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-3 rounded-md mb-6 animate-fadeIn">
              {success}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label htmlFor="email" className="block text-sm font-medium text-[#a1a1a1] mb-2">
                Email Address
              </label>
              <input
                id="email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                className="w-full px-4 py-3 bg-[#323232] border border-[#3a3a3a] text-[#ededed] placeholder-[#6b6b6b] rounded-md focus:ring-2 focus:ring-[#3ecf8e] focus:border-transparent transition-all duration-200"
                placeholder="you@example.com"
              />
              <p className="mt-2 text-xs text-[#6b6b6b]">
                We'll send a secure link to reset your password.
              </p>
            </div>

            <button
              type="submit"
              disabled={isLoading}
              className="w-full bg-[#3ecf8e] hover:bg-[#24b47e] text-black py-3 px-4 rounded-md font-medium focus:outline-none focus:ring-2 focus:ring-[#3ecf8e] focus:ring-offset-2 focus:ring-offset-[#2a2a2a] transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed glow-hover"
            >
              {isLoading ? (
                <span className="flex items-center justify-center">
                  <svg className="animate-spin -ml-1 mr-3 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Sending...
                </span>
              ) : (
                'Send Reset Link'
              )}
            </button>
          </form>

          <p className="mt-6 text-center text-sm text-[#6b6b6b]">
            Remember your password?{' '}
            <Link to="/login" className="text-[#3ecf8e] hover:text-[#24b47e] font-medium transition-colors">
              Sign in
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
