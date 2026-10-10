import type { CapacitorConfig } from '@capacitor/cli';

const appUrl = process.env.CAPACITOR_APP_URL || 'https://your-domain.example';

const config: CapacitorConfig = {
    appId: 'com.esnad.institutions',
    appName: 'Esnad Institutions',
    webDir: 'public',
    server: {
        url: appUrl,
        cleartext: appUrl.startsWith('http://'),
        androidScheme: 'https',
    },
};

export default config;
