const mysql = require('mysql2/promise');
const fs = require('fs');
const path = require('path');

async function importDb() {
  const phpConfigPath = path.join(__dirname, 'config', 'db_config.php');
  if (!fs.existsSync(phpConfigPath)) {
    throw new Error('config/db_config.php not found. Please create it and add your Aiven credentials first.');
  }

  console.log('Reading database credentials from config/db_config.php...');
  const phpConfig = fs.readFileSync(phpConfigPath, 'utf8');
  
  // Extract database details using Regex
  const host = phpConfig.match(/\$host\s*=\s*['"]([^'"]+)['"]/)[1];
  const user = phpConfig.match(/\$user\s*=\s*['"]([^'"]+)['"]/)[1];
  const pass = phpConfig.match(/\$pass\s*=\s*['"]([^'"]+)['"]/)[1];
  const db = phpConfig.match(/\$db\s*=\s*['"]([^'"]+)['"]/)[1];
  const port = parseInt(phpConfig.match(/\$port\s*=\s*(\d+)/)[1]);

  console.log(`Connecting to remote database ${db} on ${host}:${port}...`);

  const connection = await mysql.createConnection({
    host,
    port,
    user,
    password: pass,
    database: db,
    ssl: {
      rejectUnauthorized: false
    },
    multipleStatements: true
  });

  console.log('Connected to Aiven MySQL successfully!');
  
  console.log('Reading database.sql file...');
  const sql = fs.readFileSync(path.join(__dirname, 'database.sql'), 'utf8');
  
  console.log('Importing database schema and seed data...');
  await connection.query(sql);
  
  console.log('Import completed successfully!');
  await connection.end();
}

importDb().catch((err) => {
  console.error('Import failed:', err.message);
  process.exit(1);
});
