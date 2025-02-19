<?php
class CreateUserHTMLPage extends GTKHTMLPage
{
    public function processPost()
    {
        $users = $_POST['users'];
        $personaDataAccess = DataAccessManager::get('persona');
        $flatRoleDataAccess = DataAccessManager::get('flat_roles');

        foreach ($users as $user) {
            $cedula = $user['cedula'];
            $nombres = $user['nombres'];
            $apellidos = $user['apellidos'];
            $email = $user['email'];
            $password = $user['password'];
            $roleId = $user['role_id'];

            $userData = [
                'cedula' => $cedula,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'email' => $email,
                'password' => $password
            ];

            // Crear el usuario y obtener el ID del usuario recién creado
            $userId = $personaDataAccess->createUserIfNotExists($userData);
            error_log("User ID: " . $userId); // Depuración

            if ($userId) {
                // Asignar el rol al usuario
                $roleResult = $flatRoleDataAccess->assignRolesToUser($userId, [$roleId]);
                error_log("Role Assignment Result: " . json_encode($roleResult)); // Depuración

                if ($roleResult) {
                    $this->messages[] = json_encode(['success' => true, 'message' => 'Usuario y rol asignado exitosamente.']);
                } else {
                    $this->messages[] = json_encode(['success' => false, 'message' => 'Usuario creado, pero error al asignar el rol.']);
                }
            } else {
                $this->messages[] = json_encode(['success' => false, 'message' => 'Error al Crear el Usuario.']);
            }
        }
    }

    public function renderMessages()
    {
        $toReturn = "";

        if (count($this->messages) > 0)
        {
            $toReturn .= "<div class='alert'>";
            foreach ($this->messages as $message)
            {
                if (is_string($message))
                {
                    $decodedMessage = json_decode($message, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $toReturn .= "<p class='font-bold'>" . htmlspecialchars($decodedMessage['message']) . "</p>";
                    } else {
                        $toReturn .= "<p class='font-bold'>" . htmlspecialchars($message) . "</p>";
                    }
                }
                else
                {
                    $toReturn .= "<p class='font-bold'>" . print_r($message, true) . "</p>";
                }
            }
            $toReturn .= "</div>";
        }

        return $toReturn;
    }

    public function renderBody()
    {
        $rolesDataAccess = DataAccessManager::get('roles');
        $roles = $rolesDataAccess->selectAll();

        ob_start(); ?>

        <style>
            .container {
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
            }
            .alert {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
                padding: 10px;
                margin-bottom: 20px;
                border-radius: 5px;
            }
            .form-group {
                margin-bottom: 15px;
            }
            .form-group label {
                display: block;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .form-group input,
            .form-group select {
                width: 100%;
                padding: 8px;
                border: 1px solid #ccc;
                border-radius: 4px;
                box-sizing: border-box;
            }
            .form-group input:focus,
            .form-group select:focus {
                border-color: #007bff;
                outline: none;
            }
            .btn {
                background-color: #007bff;
                color: white;
                padding: 10px 15px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
            .btn:hover {
                background-color: #0056b3;
            }
            .btn-add {
                background-color: #28a745;
                color: white;
                padding: 10px 15px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                margin-top: 10px;
            }
            .btn-add:hover {
                background-color: #218838;
            }
            .user-form {
                border: 1px solid #ccc;
                padding: 15px;
                margin-bottom: 15px;
                border-radius: 5px;
            }
        </style>
    
        <div class="container">
            <h1 class="text-2xl font-bold mb-4">Crear Nuevos Usuarios</h1>

            <?php
            echo $this->renderMessages();
            ?>

            <form action="<?php echo $_SERVER['REQUEST_URI'] ?? ''; ?>" method="POST" id="userForm" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                <div id="userFormsContainer">
                    <div class="user-form">
                        <div class="form-group">
                            <label for="cedula">Cédula:</label>
                            <input type="text" name="users[0][cedula]" required>
                        </div>
                        <div class="form-group">
                            <label for="nombres">Nombres:</label>
                            <input type="text" name="users[0][nombres]" required>
                        </div>
                        <div class="form-group">
                            <label for="apellidos">Apellidos:</label>
                            <input type="text" name="users[0][apellidos]" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Correo Electrónico:</label>
                            <input type="email" name="users[0][email]" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Contraseña:</label>
                            <input type="password" name="users[0][password]" required>
                        </div>
                        <div class="form-group">
                            <label for="role_id">Rol:</label>
                            <select name="users[0][role_id]" required>
                                <option value="">Seleccione un Rol</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo htmlspecialchars($role['id']); ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-add" onclick="addUserForm()">Agregar Otro Usuario</button>
                <div class="form-group">
                    <input type="submit" value="Crear Usuarios" class="btn">
                </div>
            </form>
        </div>

        <script>
            let userFormCount = 1;

            function addUserForm() {
                const userFormsContainer = document.getElementById('userFormsContainer');
                const newUserForm = document.createElement('div');
                newUserForm.classList.add('user-form');
                newUserForm.innerHTML = `
                    <div class="form-group">
                        <label for="cedula">Cédula:</label>
                        <input type="text" name="users[${userFormCount}][cedula]" required>
                    </div>
                    <div class="form-group">
                        <label for="nombres">Nombres:</label>
                        <input type="text" name="users[${userFormCount}][nombres]" required>
                    </div>
                    <div class="form-group">
                        <label for="apellidos">Apellidos:</label>
                        <input type="text" name="users[${userFormCount}][apellidos]" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Correo Electrónico:</label>
                        <input type="email" name="users[${userFormCount}][email]" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña:</label>
                        <input type="password" name="users[${userFormCount}][password]" required>
                    </div>
                    <div class="form-group">
                        <label for="role_id">Rol:</label>
                        <select name="users[${userFormCount}][role_id]" required>
                            <option value="">Seleccione un Rol</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo htmlspecialchars($role['id']); ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                `;
                userFormsContainer.appendChild(newUserForm);
                userFormCount++;
            }
        </script>
    
        <?php return ob_get_clean();
    }
}