# 🛠️ Setup Procedure

This guide explains how to set up and run the University Platform on your local machine.

## 1) Prepare your environment

Make sure these are installed on your computer:

- PHP
- MySQL or MariaDB
- XAMPP, WAMP, or any local web server

## 2) Place the project locally

Copy the project folder into your server directory, usually:

```text
C:\xampp\htdocs\university_platform
```

If you are using WAMP, place it in the `www` folder instead.

## 3) Configure the database

Open the configuration file and update the database settings:

- `config.php`

Set the correct values for:

- database host
- database username
- database password
- database name

## 4) Start the server

Start Apache and MySQL in XAMPP or your preferred local server tool.

## 5) Open the application

Visit this URL in your browser:

```text
http://localhost/university_platform/
```

## 6) Log in and begin

Once the app loads, use your created admin, teacher, or student account to begin using the system.

## ✅ Important reminders

- Keep your database credentials private.
- Do not commit sensitive files to Git.
- Update `.gitignore` regularly to avoid uploading local files.

## 💡 Troubleshooting tips

- If the page does not load, check whether Apache is running.
- If the database connection fails, confirm your credentials in `config.php`.
- If PHP errors appear, make sure the PHP version supports the project requirements.

## 📌 Final note

Once everything is configured correctly, the application should run smoothly in your local environment.
