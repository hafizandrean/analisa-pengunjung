import React, { createContext, useContext, useState, useEffect } from 'react';
import { User, AuthState } from '../types/api';
import { api } from '../services/api';

interface AuthContextType extends AuthState {
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [state, setState] = useState<AuthState>({
    user: null,
    token: localStorage.getItem('abj_auth_token'),
    isAuthenticated: !!localStorage.getItem('abj_auth_token'),
    isLoading: true,
  });

  useEffect(() => {
    const initAuth = async () => {
      const token = localStorage.getItem('abj_auth_token');
      if (token) {
        try {
          const user = await api.auth.getMe();
          setState({
            user,
            token,
            isAuthenticated: true,
            isLoading: false,
          });
          return;
        } catch {
          // If token expired
          localStorage.removeItem('abj_auth_token');
        }
      }
      setState({
        user: null,
        token: null,
        isAuthenticated: false,
        isLoading: false,
      });
    };

    initAuth();
  }, []);

  const login = async (email: string, password: string) => {
    setState(prev => ({ ...prev, isLoading: true }));
    try {
      const { token, user } = await api.auth.login(email, password);
      setState({
        user,
        token,
        isAuthenticated: true,
        isLoading: false,
      });
    } catch (err: any) {
      setState(prev => ({ ...prev, isLoading: false }));
      throw err;
    }
  };

  const logout = () => {
    api.auth.logout();
    setState({
      user: null,
      token: null,
      isAuthenticated: false,
      isLoading: false,
    });
  };

  return (
    <AuthContext.Provider value={{ ...state, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
