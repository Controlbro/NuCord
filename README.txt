NuCord install steps

1. Create MySQL database
   In DirectAdmin or your hosting panel, create a MySQL database and database user. Grant the user all privileges on the database.

2. Edit config.php
   Update DB_HOST, DB_NAME, DB_USER, and DB_PASS. Most shared hosts use localhost for DB_HOST.
   Optional but recommended: set SETUP_KEY to a secret string before uploading.

3. Visit setup.php
   Open https://your-domain.example/setup.php in your browser. If SETUP_KEY is set, visit setup.php?key=YOUR_SECRET.
   Click "Create / Update Tables". Check "Seed demo users and messages" if you want demo accounts:
   - demo@example.com / password
   - alex@example.com / password
   - sam@example.com / password

4. Delete or lock setup.php after setup
   For security, delete setup.php from the server after the tables are created, or keep SETUP_KEY set to a private value.

5. Register/login
   Visit index.php, register a user, add friends by username, accept friend requests, and start direct message conversations.

Required files
- config.php
- setup.php
- index.php
- login.php
- register.php
- logout.php
- api/messages.php
- api/send_message.php
- api/typing.php
- api/friends.php
- assets/style.css
- assets/app.js
