# Bank CSV Downloader

Automated CSV downloader for ING Italy and PostePay Evolution using Playwright.

## Overview

This Node.js project automates the download of transaction CSV files from:
- **ING Italy** (main account)
- **Poste Italiane - Postepay Evolution**

The automation handles navigation and CSV export, while login and 2FA are performed manually by the user.

## Requirements

- Node.js 18 or higher
- Windows OS
- Chrome/Chromium browser

## Setup

1. **Initialize npm** (if not already done):
   ```bash
   npm init -y
   ```

2. **Install Playwright**:
   ```bash
   npm install playwright
   ```

3. **Install Chromium browser**:
   ```bash
   npx playwright install chromium
   ```

## Usage

Run the script:
```bash
node bank.js
```

## How It Works

1. **ING Download**:
   - Opens Chromium browser (visible)
   - Navigates to ING login page
   - Waits for you to login and complete 2FA
   - Press ENTER in terminal when ready
   - Automatically navigates to transactions page
   - Clicks export CSV button
   - Saves as `ing.csv` in project root

2. **PostePay Download**:
   - Opens PostePay login page
   - Waits for you to login and complete 2FA
   - Press ENTER in terminal when ready
   - Automatically navigates to transactions page
   - Clicks export CSV button
   - Saves as `postepay.csv` in project root

3. **Completion**:
   - Browser closes automatically
   - CSV files are saved in the project root

## Important Notes

- **No credentials stored**: All login is manual
- **2FA required**: You must complete 2FA manually
- **Browser visible**: Browser runs in visible mode (not headless) for debugging
- **Manual intervention**: Script waits for ENTER after each login step
- **Personal use only**: This is for personal automation, not for bypassing security

## Troubleshooting

If the script fails to find export buttons:
- The website layout may have changed
- Check the browser window to see current page
- You may need to manually navigate to transactions page
- Update selectors in `bank.js` if needed

## Files Generated

- `ing.csv` - ING Italy transactions
- `postepay.csv` - PostePay Evolution transactions

## License

MIT
