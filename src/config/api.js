// API Base URL
export const API_BASE_URL = 'http://127.0.0.1:8000/stepik_parser_test/public/api/v1';

// Get headers for API requests
export const getHeaders = () => {
  // Получаем CSRF-токен из мета-тега
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  
  return {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': token || ''
  };
}; 