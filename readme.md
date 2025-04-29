# M-Pesa SMS Parser CLI

A PHP-based command-line tool for parsing M-Pesa SMS backup XML files and extracting incoming transactions into a CSV file.

## 📦 Features

- Parses SMS backup files (e.g., from SMS Backup & Restore app)
- Extracts M-Pesa received payments from a specific sender
- Saves unique transactions to `mpesa_transactions.csv`
- Detects and skips duplicates
- Works with `sms-*.xml` files in the current directory

---

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/gogilo2003/sms-parser.git
cd sms-parser
```

### 2. Install Globally via Composer

```bash
composer global config repositories.sms-parser path $(pwd)
composer global require gogilo/sms-parser:dev-main
```

> If you push this to GitHub, you can later install it directly via:
>
> ```bash
> composer global require gogilo/sms-parser
> ```

---

## ✅ Usage

In any directory with `sms-*.xml` files:

```bash
parse-sms "JOHN DOE"
```

### Arguments

- `"JOHN DOE"` — The name of the person you're expecting M-Pesa payments from. It should appear exactly as shown in the SMS body.

---

## 📂 Output

A CSV file `mpesa_transactions.csv` will be created with the following columns:

- `Date`
- `Mpesa Reference`
- `From Name`
- `From Phone`
- `Amount`

Duplicates (based on reference code) are automatically skipped.

---

## 📘 Example

```bash
parse-sms "PETER MAINA"
```

Output:

```
Processing sms-backup-2024.xml
  Potential match found!
  MATCHED TRANSACTION:
    Ref: ABC123XYZ
    From: PETER MAINA
    Phone: 0712345678
    Amount: 1,000.00
    Date: 24/04/24 at 3:40 PM

RESULTS:
- Found 1 new transactions
- Skipped 0 duplicate references
- Output saved to mpesa_transactions.csv
```

---

## 🔧 Requirements

- PHP 7.4+
- Composer

---

## 📄 License

MIT

---

## 🙏 Acknowledgements

Created by [Ogilo G.D. Ouma](https://github.com/gogilo2003)  
SMS format based on Safaricom M-Pesa messages
