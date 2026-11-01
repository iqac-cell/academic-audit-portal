-- Read-only login for the Academic Audit directory (QSphereDirectoryProvider).
-- Run as an administrator on the Q-Sphere instance (localhost,14330), after Q-Sphere has created
-- the views dbo.sdg_departments and dbo.sdg_users. Choose the password yourself and put it only in
-- the Academic Audit .env (QSPHERE_DB_PASSWORD); do not save it in this file.

USE master;
GO
IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = N'audit_reader')
    CREATE LOGIN audit_reader WITH PASSWORD = N'<password>', CHECK_POLICY = ON, DEFAULT_DATABASE = qsphere;
GO

USE qsphere;
GO
IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = N'audit_reader')
    CREATE USER audit_reader FOR LOGIN audit_reader WITH DEFAULT_SCHEMA = dbo;
GO
GRANT SELECT ON OBJECT::dbo.sdg_departments TO audit_reader;
GRANT SELECT ON OBJECT::dbo.sdg_users TO audit_reader;
GO
