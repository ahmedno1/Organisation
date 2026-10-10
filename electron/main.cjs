const { app, BrowserWindow, shell, ipcMain } = require('electron');
const path = require('path');
const fs = require('fs');

function getConfiguredUrl() {
  try {
    const cfgPath = path.join(__dirname, 'desktop.config.json');
    const raw = fs.readFileSync(cfgPath, 'utf8');
    const cfg = JSON.parse(raw);
    if (cfg && typeof cfg.productionUrl === 'string' && cfg.productionUrl.trim() !== '') {
      return cfg.productionUrl.trim();
    }
  } catch (_) {
    // Use fallback when config file is missing or invalid.
  }
  return 'http://127.0.0.1:8000';
}

const DEFAULT_URL = process.env.DESKTOP_APP_URL || getConfiguredUrl();
let mainWindow;

function createMainWindow() {
  mainWindow = new BrowserWindow({
    width: 1366,
    height: 820,
    minWidth: 1024,
    minHeight: 640,
    autoHideMenuBar: true,
    show: false,
    webPreferences: {
      preload: path.join(__dirname, 'preload.cjs'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
    },
  });

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    shell.openExternal(url);
    return { action: 'deny' };
  });

  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
  });

  mainWindow.webContents.on('did-fail-load', () => {
    mainWindow.loadFile(path.join(__dirname, 'offline.html'), {
      query: { target: DEFAULT_URL },
    });
  });

  mainWindow.loadURL(DEFAULT_URL);
}

ipcMain.handle('desktop:reload', () => {
  if (mainWindow) {
    mainWindow.loadURL(DEFAULT_URL);
  }
});

app.whenReady().then(() => {
  createMainWindow();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createMainWindow();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});
