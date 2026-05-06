CREATE DATABASE IF NOT EXISTS rellenadorinforme CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rellenadorinforme;

CREATE TABLE IF NOT EXISTS `informacion_semana` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `hoja1` VARCHAR(10) DEFAULT NULL,
    `hoja2` VARCHAR(10) DEFAULT NULL,
    `diainicio` INTEGER DEFAULT NULL,
    `diafin` INTEGER DEFAULT NULL,
    `mes` VARCHAR(60) DEFAULT NULL,
    `ano` VARCHAR(4) DEFAULT NULL,
    `firma_alumno` VARCHAR(255) DEFAULT NULL,
    `firma_profe` VARCHAR(255) DEFAULT NULL,
    `firma_tutor` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `informacion_del_centro_docente` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre_y_apellidos` VARCHAR(255) DEFAULT NULL,
    `nombre_alumno` VARCHAR(255) DEFAULT NULL,
    `centro_docente` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `centro_trabajo` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre_centro_trabajo` VARCHAR(255) DEFAULT NULL,
    `tutor_trabajo` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ciclos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(255) DEFAULT NULL,
    `grado` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dias` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre` VARCHAR(260) DEFAULT NULL,
    `actividades` VARCHAR(260) DEFAULT NULL,
    `tiempo` VARCHAR(260) DEFAULT NULL,
    `observaciones` VARCHAR(260) DEFAULT NULL,
    `id_semana` INT UNSIGNED DEFAULT NULL,
    `posicion` TINYINT UNSIGNED DEFAULT NULL,
    PRIMARY KEY(`id`),
    KEY idx_dias_semana (id_semana),
    CONSTRAINT fk_dias_semana
        FOREIGN KEY(`id_semana`) REFERENCES `informacion_semana`(`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `centroeduc_semana` (
    `id_semana` INT UNSIGNED NOT NULL,
    `id_centro_docente` INT UNSIGNED NOT NULL,
    PRIMARY KEY(`id_semana`, `id_centro_docente`),
    KEY idx_centroeduc_semana (id_semana, id_centro_docente),
    CONSTRAINT fk_centroeduc_semana
        FOREIGN KEY(`id_semana`) REFERENCES `informacion_semana`(`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_centroeduc_centro
        FOREIGN KEY(`id_centro_docente`) REFERENCES `informacion_del_centro_docente`(`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `centrotrabajo_semana` (
    `id_centro_trabajo` INT UNSIGNED NOT NULL,
    `id_semana` INT UNSIGNED NOT NULL,
    PRIMARY KEY(`id_centro_trabajo`, `id_semana`),
    KEY idx_centrotrabajo_semana (id_centro_trabajo, id_semana),
    CONSTRAINT fk_centrotrabajo_semana_trabajo
        FOREIGN KEY(`id_centro_trabajo`) REFERENCES `centro_trabajo`(`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_centrotrabajo_semana_semana
        FOREIGN KEY(`id_semana`) REFERENCES `informacion_semana`(`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ciclos_semana` (
    `id_semana` INT UNSIGNED NOT NULL,
    `id_ciclo` INT UNSIGNED NOT NULL,
    PRIMARY KEY(`id_semana`, `id_ciclo`),
    KEY idx_ciclos_semana (id_semana, id_ciclo),
    CONSTRAINT fk_ciclos_semana_semana
        FOREIGN KEY(`id_semana`) REFERENCES `informacion_semana`(`id`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ciclos_semana_ciclo
        FOREIGN KEY(`id_ciclo`) REFERENCES `ciclos`(`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
