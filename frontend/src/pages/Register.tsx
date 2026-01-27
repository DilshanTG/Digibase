import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

export function Register() {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [acceptTerms, setAcceptTerms] = useState(false);
  const [error, setError] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const { register } = useAuth();
  const navigate = useNavigate();

  const getPasswordStrength = () => {
    if (!password) return { level: 0, text: '', color: '' };
    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    if (strength <= 2) return { level: strength, text: 'Weak', color: 'bg-red-500' };
    if (strength <= 3) return { level: strength, text: 'Medium', color: 'bg-yellow-500' };
    return { level: strength, text: 'Strong', color: 'bg-green-500' };
  };

  const passwordStrength = getPasswordStrength();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (password !== passwordConfirmation) {
      setError('Passwords do not match');
      return;
    }

    if (!acceptTerms) {
      setError('Please accept the terms and conditions');
      return;
    }

    setIsLoading(true);

    try {
      await register(name, email, password, passwordConfirmation);
      navigate('/dashboard');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Registration failed');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-[#1c1c1c] py-12">
      <div className="max-w-md w-full mx-4 animate-slideUp">
        <div className="bg-[#2a2a2a] border border-[#3a3a3a] rounded-lg p-8">
          <div className="text-center mb-8">
            <div className="w-12 h-12 bg-gradient-to-br from-[#3ecf8e] to-[#24b47e] rounded-lg flex items-center justify-center mx-auto mb-4">
              <span className="text-white font-bold text-xl">D</span>
            </div>
            <h1 className="text-2xl font-semibold text-[#ededed]">Create account</h1>
            <p className="text-[#6b6b6b] mt-2">Join Digibase today</p>
          </div>

          {error && (
            <div className="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-md mb-6 animate-fadeIn">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label htmlFor="name" className="block text-sm font-medium text-[#a1a1a1] mb-2">
                Full Name
              </label>
              <input
                id="name"
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
                className="w-full px-4 py-3 bg-[#323232] border border-[#3a3a3a] text-[#ededed] placeholder-[#6b6b6b] rounded-md focus:ring-2 focus:ring-[#3ecf8e] focus:border-transparent transition-all duration-200"
                placeholder="John Doe"
              />
            </div>

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
            </div>

            <div>
              <label htmlFor="password" className="block text-sm font-medium text-[#a1a1a1] mb-2">
                Password
              </label>
              <input
                id="password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                minLength={8}
                className="w-full px-4 py-3 bg-[#323232] border border-[#3a3a3a] text-[#ededed] placeholder-[#6b6b6b] rounded-md focus:ring-2 focus:ring-[#3ecf8e] focus:border-transparent transition-all duration-200"
                placeholder="••••••••"
              />
              {password && (
                <div className="mt-3 animate-fadeIn">
                  <div className="flex items-center gap-2 mb-2">
                    <div className="flex-1 h-1.5 bg-[#323232] rounded-full overflow-hidden">
                      <div
                        className={`h-full ${passwordStrength.color} transition-all duration-300`}
                        style={{ width: `${(passwordStrength.level / 5) * 100}%` }}
                      ></div>
                    </div>
                    <span className={`text-xs font-medium ${passwordStrength.level <= 2 ? 'text-red-400' : passwordStrength.level <= 3 ? 'text-yellow-400' : 'text-green-400'}`}>
                      {passwordStrength.text}
                    </span>
                  </div>
                  <ul className="grid grid-cols-2 gap-1">
                    <li className={`text-xs flex items-center gap-1 ${password.length >= 8 ? 'text-green-400' : 'text-[#6b6b6b]'}`}>
                      <span className="text-[10px]">{password.length >= 8 ? '✓' : '•'}</span> 8+ chars
                    </li>
                    <li className={`text-xs flex items-center gap-1 ${/[A-Z]/.test(password) ? 'text-green-400' : 'text-[#6b6b6b]'}`}>
                      <span className="text-[10px]">{/[A-Z]/.test(password) ? '✓' : '•'}</span> Uppercase
                    </li>
                    <li className={`text-xs flex items-center gap-1 ${/[a-z]/.test(password) ? 'text-green-400' : 'text-[#6b6b6b]'}`}>
                      <span className="text-[10px]">{/[a-z]/.test(password) ? '✓' : '•'}</span> Lowercase
                    </li>
                    <li className={`text-xs flex items-center gap-1 ${/[0-9]/.test(password) ? 'text-green-400' : 'text-[#6b6b6b]'}`}>
                      <span className="text-[10px]">{/[0-9]/.test(password) ? '✓' : '•'}</span> Number
                    </li>
                  </ul>
                </div>
              )}
            </div>

            <div>
              <label htmlFor="passwordConfirmation" className="block text-sm font-medium text-[#a1a1a1] mb-2">
                Confirm Password
              </label>
              <input
                id="passwordConfirmation"
                type="password"
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                required
                className="w-full px-4 py-3 bg-[#323232] border border-[#3a3a3a] text-[#ededed] placeholder-[#6b6b6b] rounded-md focus:ring-2 focus:ring-[#3ecf8e] focus:border-transparent transition-all duration-200"
                placeholder="••••••••"
              />
            </div>

            <div className="pt-2">
              <label className="flex items-start cursor-pointer">
                <input
                  type="checkbox"
                  checked={acceptTerms}
                  onChange={(e) => setAcceptTerms(e.target.checked)}
                  className="h-4 w-4 mt-1 text-[#3ecf8e] bg-[#323232] border-[#3a3a3a] rounded focus:ring-[#3ecf8e]"
                />
                <span className="ml-2 text-sm text-[#a1a1a1]">
                  I accept the{' '}
                  <a href="#" className="text-[#3ecf8e] hover:text-[#24b47e]">
                    Terms of Service
                  </a>{' '}
                  and{' '}
                  <a href="#" className="text-[#3ecf8e] hover:text-[#24b47e]">
                    Privacy Policy
                  </a>
                </span>
              </label>
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
                  Creating account...
                </span>
              ) : (
                'Create account'
              )}
            </button>
          </form>

          <p className="mt-6 text-center text-sm text-[#6b6b6b]">
            Already have an account?{' '}
            <Link to="/login" className="text-[#3ecf8e] hover:text-[#24b47e] font-medium transition-colors">
              Sign in
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
