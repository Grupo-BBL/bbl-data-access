<?php
use PhpOffice\PhpSpreadsheet\IOFactory;

class CreateUserHTMLPage extends GTKHTMLPage
{
    public function processPost()
    {
        if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == UPLOAD_ERR_OK) {
            $this->processExcelFile($_FILES['excel_file']['tmp_name']);
        } else {
            $this->processFormInput($_POST['users']);
        }
    }

    private function processExcelFile($filePath)
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
    
        $personaDataAccess = DataAccessManager::get('persona');
    
        foreach ($rows as $index => $row) {
            if ($index == 0) continue; 
    
            $userData = [
                'cedula' => $row[0],
                'nombres' => $row[1],
                'apellidos' => $row[2],
                'email' => $row[3],
                'password' => $row[4]
            ];
            $roleIds = explode(',', $row[5]);
    
            // Llamar al nuevo método en personaDataAccess
            $result = $personaDataAccess->createUserWithRoles($userData, $roleIds);
    
            // Registrar el resultado
            $this->messages[] = json_encode($result);
        }
    }

    private function processFormInput($users)
    {
        $personaDataAccess = DataAccessManager::get('persona');

      foreach ($users as $user) {
         $userData = [
            'cedula' => $user['cedula'],
            'nombres' => $user['nombres'],
            'apellidos' => $user['apellidos'],
            'email' => $user['email'],
            'password' => $user['password']
        ];
        $roleIds = $user['role_ids'];

        
        $result = $personaDataAccess->createUserWithRoles($userData, $roleIds);

      
        $this->messages[] = json_encode($result);
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
                background-color:rgb(255, 252, 252);
                color:rgb(0, 0, 0);
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

            <form action="<?php echo $_SERVER['REQUEST_URI'] ?? ''; ?>" method="POST" enctype="multipart/form-data" id="userForm" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
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
                            <label for="role_ids">Roles:</label>
                            <?php foreach ($roles as $role): ?>
                                <div>
                                    <input type="checkbox" name="users[0][role_ids][]" value="<?php echo htmlspecialchars($role['id']); ?>">
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-add" onclick="addUserForm()">Agregar Otro Usuario</button>
                <div class="form-group">
                    <label for="excel_file">O cargar archivo de Excel:</label>
                    <input type="file" name="excel_file" id="excel_file" accept=".xlsx, .xls">
                </div>
                <div class="form-group">
                    <input type="submit" value="Crear Usuarios" class="btn">
                </div>
            </form>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>
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
                        <label for="role_ids">Roles:</label>
                        <?php foreach ($roles as $role): ?>
                            <div>
                                <input type="checkbox" name="users[${userFormCount}][role_ids][]" value="<?php echo htmlspecialchars($role['id']); ?>">
                                <?php echo htmlspecialchars($role['name']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                `;
                userFormsContainer.appendChild(newUserForm);
                userFormCount++;
            }

            document.getElementById('excel_file').addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const data = new Uint8Array(e.target.result);
                        const workbook = XLSX.read(data, { type: 'array' });
                        const sheetName = workbook.SheetNames[0];
                        const worksheet = workbook.Sheets[sheetName];
                        const json = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

                        // Clear existing forms
                        const userFormsContainer = document.getElementById('userFormsContainer');
                        userFormsContainer.innerHTML = '';
                        userFormCount = 0;

                        // Skip header row and add forms for each row
                        json.slice(1).forEach((row, index) => {
                            addUserForm();
                            document.querySelector(`input[name="users[${index}][cedula]"]`).value = row[0];
                            document.querySelector(`input[name="users[${index}][nombres]"]`).value = row[1];
                            document.querySelector(`input[name="users[${index}][apellidos]"]`).value = row[2];
                            document.querySelector(`input[name="users[${index}][email]"]`).value = row[3];
                            document.querySelector(`input[name="users[${index}][password]"]`).value = row[4];
                            const roleIds = row[5].split(',');
                            roleIds.forEach(roleId => {
                                const checkbox = document.querySelector(`input[name="users[${index}][role_ids][]"][value="${roleId}"]`);
                                if (checkbox) {
                                    checkbox.checked = true;
                                }
                            });
                        });
                    };
                    reader.readAsArrayBuffer(file);
                }
            });
        </script>
    
        <?php return ob_get_clean();
    }
}