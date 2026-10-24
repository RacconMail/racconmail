/**
 * Raccon Mail - Universal Web Push & Browser Notifications System
 * Enables push notifications across inbox, forum, and stories
 */
(function(window) {
  'use strict';

  const RacconNotifications = {
    isSupported() {
      return ('Notification' in window);
    },

    permission() {
      return this.isSupported() ? Notification.permission : 'unsupported';
    },

    async requestPermission() {
      if (!this.isSupported()) return 'unsupported';
      if (Notification.permission === 'granted') return 'granted';
      try {
        const perm = await Notification.requestPermission();
        return perm;
      } catch (err) {
        return Notification.permission;
      }
    },

    show({ title, body, icon = '/raccoon-logo.png', tag = 'raccon-notif', url = null, badge = '/favicon-32x32.png' }) {
      if (!this.isSupported()) return null;

      if (Notification.permission === 'granted') {
        try {
          const notif = new Notification(title, {
            body: body || '',
            icon: icon,
            badge: badge,
            tag: tag,
            vibrate: [150, 80, 150]
          });

          if (url) {
            notif.onclick = function() {
              window.focus();
              window.location.href = url;
            };
          }
          return notif;
        } catch (e) {
          console.warn('Browser notification error:', e);
          return null;
        }
      } else if (Notification.permission !== 'denied') {
        this.requestPermission();
      }
      return null;
    }
  };

  window.RacconNotifications = RacconNotifications;

  // Auto-request permission on first user interaction if default
  window.addEventListener('click', function initNotifOnFirstClick() {
    if (RacconNotifications.isSupported() && Notification.permission === 'default') {
      RacconNotifications.requestPermission();
    }
    window.removeEventListener('click', initNotifOnFirstClick);
  }, { once: true });

})(window);
