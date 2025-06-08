import { defineStore } from 'pinia';
import { API_BASE_URL, getHeaders } from '@/config/api';

export const usePlaylistStore = defineStore('playlists', {
  state: () => ({
    playlists: [],
    loading: false,
    error: null,
    selectedPlaylist: null
  }),

  actions: {
    async fetchUserPlaylists() {
      this.loading = true;
      try {
        const response = await fetch(`${API_BASE_URL}/playlists`, {
          method: 'GET',
          headers: getHeaders(),
          credentials: 'include'
        });
        const data = await response.json();
        if (data.success) {
          this.playlists = data.data;
          this.error = null;
        } else {
          throw new Error(data.message || 'Failed to fetch playlists');
        }
      } catch (error) {
        this.error = error.message;
        this.playlists = [];
      } finally {
        this.loading = false;
      }
    },

    // ... остальные методы остаются без изменений ...
  }
}); 