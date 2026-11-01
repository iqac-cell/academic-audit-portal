-- Lets the Q-Sphere Portal Home read who holds which Academic Audit role (view dbo.iqac_user_roles).
-- academic_audit is on the default instance MSSQLSERVER at tcp:localhost,14330 (same as qsphere and sdg_portal),
-- where the login iqac_reader already exists. Run as an administrator after "php artisan migrate --force":
--     sqlcmd -S tcp:localhost,14330 -E -C -i grant-iqac-reader.sql
-- Never run this against MSSQLSERVER01 (port 1433).

USE academic_audit;
GO

IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = N'iqac_reader')
    CREATE USER iqac_reader FOR LOGIN iqac_reader WITH DEFAULT_SCHEMA = dbo;
GO

GRANT SELECT ON OBJECT::dbo.iqac_user_roles TO iqac_reader;
GO

-- Check: should list only SELECT on iqac_user_roles.
SELECT pr.name, pe.permission_name, OBJECT_NAME(pe.major_id) AS object_name
FROM sys.database_permissions pe
JOIN sys.database_principals pr ON pr.principal_id = pe.grantee_principal_id
WHERE pr.name = N'iqac_reader';
GO
