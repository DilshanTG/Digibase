import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from './hooks/useAuth';
import { ProtectedRoute } from './components/ProtectedRoute';
import { Login } from './pages/Login';
import { Register } from './pages/Register';
import { ForgotPassword } from './pages/ForgotPassword';
import { Dashboard } from './pages/Dashboard';
import { Models } from './pages/Models';
import { ModelCreate } from './pages/ModelCreate';
import { ModelDetail } from './pages/ModelDetail';
import { Storage } from './pages/Storage';
import { Database } from './pages/Database';

import { ApiDocs } from './pages/ApiDocs';

const queryClient = new QueryClient();

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <BrowserRouter>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/register" element={<Register />} />
            <Route path="/forgot-password" element={<ForgotPassword />} />
            <Route
              path="/dashboard"
              element={
                <ProtectedRoute>
                  <Dashboard />
                </ProtectedRoute>
              }
            />
            <Route
              path="/models"
              element={
                <ProtectedRoute>
                  <Models />
                </ProtectedRoute>
              }
            />
            <Route
              path="/models/create"
              element={
                <ProtectedRoute>
                  <ModelCreate />
                </ProtectedRoute>
              }
            />
            <Route
              path="/models/:id"
              element={
                <ProtectedRoute>
                  <ModelDetail />
                </ProtectedRoute>
              }
            />
            <Route
              path="/api-docs"
              element={
                <ProtectedRoute>
                  <ApiDocs />
                </ProtectedRoute>
              }
            />
            <Route
              path="/storage"
              element={
                <ProtectedRoute>
                  <Storage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/database"
              element={
                <ProtectedRoute>
                  <Database />
                </ProtectedRoute>
              }
            />
            <Route path="/" element={<Navigate to="/dashboard" replace />} />
          </Routes>
        </BrowserRouter>
      </AuthProvider>
    </QueryClientProvider>
  );
}

export default App;
