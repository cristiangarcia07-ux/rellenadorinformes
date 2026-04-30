CREATE DATABASE IF NOT EXISTS rellenadorinforme CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rellenadorinforme;

CREATE TABLE IF NOT EXISTS informacion_semana (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    hoja1 VARCHAR(10) DEFAULT NULL,
    hoja2 VARCHAR(10) DEFAULT NULL,
    diainicio INT DEFAULT NULL,
    diafin INT DEFAULT NULL,
    mes VARCHAR(60) DEFAULT NULL,
    ano VARCHAR(4) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS informacion_del_centro_docente (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_y_apellidos VARCHAR(255) DEFAULT NULL,
    nombre_alumno VARCHAR(255) DEFAULT NULL,
    centro_docente VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS centro_trabajo (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_centro_trabajo VARCHAR(255) DEFAULT NULL,
    tutor_trabajo VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ciclos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(255) DEFAULT NULL,
    grado VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dias (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(260) DEFAULT NULL,
    actividades VARCHAR(260) DEFAULT NULL,
    tiempo VARCHAR(260) DEFAULT NULL,
    observaciones VARCHAR(260) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS relacion_semana_centro_trabajo (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_semana INT UNSIGNED NOT NULL,
    id_centro_docente INT UNSIGNED NOT NULL,
    id_ciclo INT UNSIGNED NOT NULL,
    id_centro_trabajo INT UNSIGNED NOT NULL,
    firma_alumno VARCHAR(255) DEFAULT NULL,
    firma_profe VARCHAR(255) DEFAULT NULL,
    firma_tutor VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rel_semana (id_semana),
    KEY idx_rel_centro_docente (id_centro_docente),
    KEY idx_rel_ciclo (id_ciclo),
    KEY idx_rel_centro_trabajo (id_centro_trabajo),
    CONSTRAINT fk_rel_semana
        FOREIGN KEY (id_semana) REFERENCES informacion_semana(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rel_centro_docente
        FOREIGN KEY (id_centro_docente) REFERENCES informacion_del_centro_docente(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rel_ciclo
        FOREIGN KEY (id_ciclo) REFERENCES ciclos(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rel_centro_trabajo
        FOREIGN KEY (id_centro_trabajo) REFERENCES centro_trabajo(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS relacion_semana_dia (
    id_relacion INT UNSIGNED NOT NULL,
    id_dia INT UNSIGNED NOT NULL,
    posicion TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (id_relacion, posicion),
    KEY idx_rel_dia (id_dia),
    CONSTRAINT fk_relacion_dia_relacion
        FOREIGN KEY (id_relacion) REFERENCES relacion_semana_centro_trabajo(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_relacion_dia_dia
        FOREIGN KEY (id_dia) REFERENCES dias(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
