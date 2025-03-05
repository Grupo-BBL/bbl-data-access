<?php
class ReglaRegistroServicio
{
    private static $cacheReglas = [];
    private static $cacheRolesUsuario = [];

    public function filtrarRegistrosPorReglas($modelo, $registros, $userId, $operacion = 'lectura')
    {
        if (empty($registros)) {
            return [];
        }

        if (!isset(self::$cacheRolesUsuario[$userId])) {
            $roleDataAccess = DataAccessManager::get("roles");
            $usuario = DataAccessManager::get("persona")->getById($userId);

            if ($usuario) {
                $roles = $roleDataAccess->rolesForUser($usuario);
                $roleIds = array_column($roles, 'id');
                self::$cacheRolesUsuario[$userId] = $roleIds;
            } else {
                self::$cacheRolesUsuario[$userId] = [];
            }
        }

        $roleIds = self::$cacheRolesUsuario[$userId];

        foreach ($roleIds as $roleId) {
            $rol = DataAccessManager::get("roles")->getById($roleId);
            if (isset($rol['is_root_role']) && $rol['is_root_role'] == 1) {
                return $registros;
            }
        }

        $cacheKey = "{$modelo}_{$operacion}_" . implode('_', $roleIds);

        if (!isset(self::$cacheReglas[$cacheKey])) {
            $reglasDataAccess = DataAccessManager::get("reglas_registros");
            $reglas = $reglasDataAccess->getReglasAplicables($modelo, $operacion, $roleIds);
            self::$cacheReglas[$cacheKey] = $reglas;
        }

        $reglas = self::$cacheReglas[$cacheKey];

        if (empty($reglas)) {
            return $registros;
        }

        $registrosFiltrados = [];

        foreach ($registros as $registro) {
            if ($this->registroCumpleReglas($registro, $reglas, $userId)) {
                $registrosFiltrados[] = $registro;
            }
        }

        return $registrosFiltrados;
    }

    private function registroCumpleReglas($registro, $reglas, $userId) 
    {
        $reglasGlobales = array_filter($reglas, function($regla) {
            return $regla['es_global'] == 1;
        });

        $reglasEspecificas = array_filter($reglas, function($regla) {
            return $regla['es_global'] != 1;
        });

        foreach ($reglasGlobales as $regla) {
            if (!$this->evaluarDominio($regla['expresion_dominio'], $registro, $userId)) {
                return false;
            }
        }

        if (!empty($reglasEspecificas)) {
            $tienePermiso = false;
            foreach ($reglasEspecificas as $regla) {
                if ($this->evaluarDominio($regla['expresion_dominio'], $registro, $userId)) {
                    $tienePermiso = true;
                    break;
                }
            }
            if (!$tienePermiso) {
                return false;
            }
        }

        return true;
    }

    private function evaluarDominio($expresionJson, $registro, $userId) 
    {
        $expresion = json_decode($expresionJson, true);

        if (!$expresion || json_last_error() !== JSON_ERROR_NONE) {
            return true;
        }

        return $this->evaluarCondicion($expresion, $registro, $userId);
    }

    private function evaluarCondicion($condicion, $registro, $userId)
    {
        if (isset($condicion[0]) && in_array($condicion[0], ['&', '|', '!'])) {
            $operador = $condicion[0];

            switch ($operador) {
                case '&':
                    for ($i = 1; $i < count($condicion); $i++) {
                        if (!$this->evaluarCondicion($condicion[$i], $registro, $userId)) {
                            return false;
                        }
                    }
                    return true;

                case '|':
                    for ($i = 1; $i < count($condicion); $i++) {
                        if ($this->evaluarCondicion($condicion[$i], $registro, $userId)) {
                            return true;
                        }
                    }
                    return false;

                case '!':
                    if (isset($condicion[1])) {
                        return !$this->evaluarCondicion($condicion[1], $registro, $userId);
                    }
                    return false;
            }
        }

        if (isset($condicion[0]) && is_string($condicion[0])) {
            $campo = $condicion[0];
            $operador = $condicion[1];
            $valor = $condicion[2];

            if (is_string($valor) && strpos($valor, '${') === 0 && substr($valor, -1) === '}') {
                $variable = substr($valor, 2, -1);
                if ($variable === 'user_id') {
                    $valor = $userId;
                } 
                else if ($variable === 'user_qualifier') {
                    $usuario = DataAccessManager::get("persona")->getById($userId);
                    $rol = DataAccessManager::get("roles")->getById($this->currentRoleId);

                    if ($rol && isset($rol['needs_qualifier']) && $rol['needs_qualifier'] && $usuario) {
                        $relacionRolPersona = DataAccessManager::get("role_person_relationships")
                            ->getRelationshipForRolePerson($rol, $usuario);

                        if ($relacionRolPersona && isset($relacionRolPersona['qualifier'])) {
                            $valor = $relacionRolPersona['qualifier'];
                        }
                    }
                }
            }

            switch ($operador) {
                case '=':
                    return isset($registro[$campo]) && $registro[$campo] == $valor;
                case '!=':
                    return !isset($registro[$campo]) || $registro[$campo] != $valor;
                case '>':
                    return isset($registro[$campo]) && $registro[$campo] > $valor;
                case '<':
                    return isset($registro[$campo]) && $registro[$campo] < $valor;
                case '>=':
                    return isset($registro[$campo]) && $registro[$campo] >= $valor;
                case '<=':
                    return isset($registro[$campo]) && $registro[$campo] <= $valor;
                case 'in':
                    return isset($registro[$campo]) && is_array($valor) && in_array($registro[$campo], $valor);
                case 'not in':
                    return !isset($registro[$campo]) || !is_array($valor) || !in_array($registro[$campo], $valor);
                case 'like':
                    return isset($registro[$campo]) && is_string($registro[$campo]) && 
                           is_string($valor) && strpos($registro[$campo], $valor) !== false;
                case 'ilike':
                    return isset($registro[$campo]) && is_string($registro[$campo]) && 
                           is_string($valor) && stripos($registro[$campo], $valor) !== false;
                default:
                    return false;
            }
        }

        return false;
    }

    public function limpiarCache($userId = null)
    {
        if ($userId === null) {
            self::$cacheReglas = [];
            self::$cacheRolesUsuario = [];
        } else {
            unset(self::$cacheRolesUsuario[$userId]);

            foreach (self::$cacheReglas as $cacheKey => $reglas) {
                if (strpos($cacheKey, "_{$userId}_") !== false) {
                    unset(self::$cacheReglas[$cacheKey]);
                }
            }
        }
    }
}