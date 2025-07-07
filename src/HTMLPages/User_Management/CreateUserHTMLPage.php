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
                'password' => $row[4],
                'codigo_transportista' => isset($row[6]) ? $row[6] : null
            ];
            
            // Manejar roles - si no hay roles o está vacío, usar array vacío
            $roleIds = [];
            if (!empty($row[5])) {
            $roleIds = explode(',', $row[5]);
                // Limpiar espacios en blanco
                $roleIds = array_map('trim', $roleIds);
                // Filtrar valores vacíos
                $roleIds = array_filter($roleIds);
            }
    
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
            'password' => $user['password'],
            'codigo_transportista' => isset($user['codigo_transportista']) ? $user['codigo_transportista'] : null
        ];
        $roleIds = $user['role_ids'] ?? [];

        
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
        
        // Obtener lista de transportistas para el select
        $transportistaDataAccess = DataAccessManager::get('transportista');
        $transportistas = $transportistaDataAccess->selectAll();

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
                position: relative;
            }
            .btn-remove {
                position: absolute;
                top: 10px;
                right: 10px;
                background-color: transparent;
                color: #dc3545;
                border: none;
                cursor: pointer;
                font-size: 20px;
                font-weight: bold;
                line-height: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0;
                width: auto;
                height: auto;
            }
            .btn-remove:hover {
                color: #c82333;
            }
            /* Nuevos estilos para los roles */
            .roles-container {
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                padding: 10px;
                margin-top: 5px;
                max-height: 200px;
                overflow-y: auto;
            }
            .roles-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 10px;
                border-bottom: 2px solid #007bff;
                padding-bottom: 5px;
            }
            .roles-title {
                font-weight: bold;
                color: #333;
            }
            .roles-search {
                flex: 1;
                margin-left: 10px;
                max-width: 200px;
                padding: 4px 8px;
                border: 1px solid #ccc;
                border-radius: 4px;
                font-size: 14px;
            }
            .roles-search:focus {
                border-color: #007bff;
                outline: none;
                box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
            }
            .role-item.hidden {
                display: none;
            }
            .role-item {
                display: flex;
                align-items: center;
                padding: 8px;
                border-bottom: 1px solid #eee;
            }
            .role-item:last-child {
                border-bottom: none;
            }
            .role-item input[type="checkbox"] {
                width: auto;
                margin-right: 10px;
            }
            .role-item label {
                margin: 0;
                font-weight: normal;
                cursor: pointer;
                flex: 1;
            }
            .role-item:hover {
                background-color: #f8f9fa;
            }
            .form-text {
                font-size: 0.875rem;
                color: #6c757d;
                margin-top: 0.25rem;
            }
            .text-muted {
                color: #6c757d !important;
            }
            
            /* Estilos para Select2 */
            .select2-container {
                width: 100% !important;
            }
            .select2-container .select2-selection--single {
                height: 36px;
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            .select2-container--default .select2-selection--single .select2-selection__rendered {
                line-height: 36px;
                padding-right: 20px;
            }
            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 34px;
                width: 30px;
            }
            .select2-container--default .select2-results__option--highlighted[aria-selected] {
                background-color: #007bff;
            }
            .select2-search__field {
                padding: 6px !important;
            }
            /* Ocultar el botón de limpiar */
            .select2-container--default .select2-selection--single .select2-selection__clear {
                display: none !important;
            }
            .select2-container--default .select2-selection--single:focus {
                border-color: #007bff;
                outline: none;
                box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
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
                            <label for="codigo_transportista">Código de Transportista:</label>
                            <select name="users[0][codigo_transportista]" class="form-control select2-transportista" data-placeholder="Buscar transportista por código o nombre...">
                                <option value="">Seleccionar transportista (opcional)</option>
                                <?php foreach ($transportistas as $transportista): ?>
                                    <?php 
                                    $codigo = $transportistaDataAccess->valueForKey('identificador', $transportista);
                                    $nombre = $transportistaDataAccess->valueForKey('nombre', $transportista);
                                    ?>
                                    <option value="<?php echo htmlspecialchars($codigo); ?>">
                                        <?php echo htmlspecialchars($codigo . ' - ' . $nombre); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <div class="roles-header">
                                <div class="roles-title">Roles:</div>
                                <input type="text" 
                                       class="roles-search" 
                                       placeholder="Buscar roles..."
                                       oninput="filterRoles(this, 0)">
                            </div>
                            <div class="roles-container">
                            <?php foreach ($roles as $role): ?>
                                    <div class="role-item" data-role-name="<?php echo htmlspecialchars(strtolower($role['name'])); ?>">
                                        <input type="checkbox" id="role_<?php echo htmlspecialchars($role['id']); ?>" 
                                               name="users[0][role_ids][]" 
                                               value="<?php echo htmlspecialchars($role['id']); ?>">
                                        <label for="role_<?php echo htmlspecialchars($role['id']); ?>">
                                    <?php echo htmlspecialchars($role['name']); ?>
                                        </label>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-add" onclick="addUserForm()">Agregar Otro Usuario</button>
                <div class="form-group">
                    <label for="excel_file">O cargar archivo de Excel:</label>
                    <input type="file" name="excel_file" id="excel_file" accept=".xlsx, .xls">
                    <small class="form-text text-muted">
                        Formato del Excel: Cédula | Nombres | Apellidos | Email | Contraseña | Roles (IDs separados por coma) | Código Transportista (opcional)
                    </small>
                </div>
                <div class="form-group">
                    <input type="submit" value="Crear Usuarios" class="btn">
                </div>
            </form>
        </div>

        <!-- jQuery y Select2 CSS y JS -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>
        <script>
            let userFormCount = 1;

            // Inicializar Select2 en todos los selects de transportista existentes
            $(document).ready(function() {
                $('.select2-transportista').select2({
                    placeholder: 'Buscar transportista por código o nombre...',
                    allowClear: false,
                    width: '100%',
                    language: {
                        noResults: function() {
                            return "No se encontraron transportistas";
                        },
                        searching: function() {
                            return "Buscando...";
                        }
                    },
                    matcher: function(params, data) {
                        // Si no hay término de búsqueda, mostrar la opción
                        if ($.trim(params.term) === '') {
                            return data;
                        }

                        // No mostrar la opción si no hay texto
                        if (typeof data.text === 'undefined') {
                            return null;
                        }

                        // Búsqueda case-insensitive y con acentos
                        const normalizedTerm = params.term.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                        const normalizedText = data.text.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

                        // Si el texto contiene el término de búsqueda, mostrar la opción
                        if (normalizedText.indexOf(normalizedTerm) > -1) {
                            return data;
                        }

                        // Si no hay coincidencia, no mostrar la opción
                        return null;
                    }
                });
            });

            function addUserForm() {
                const userFormsContainer = document.getElementById('userFormsContainer');
                const newUserForm = document.createElement('div');
                newUserForm.classList.add('user-form');
                newUserForm.innerHTML = `
                    <button type="button" class="btn-remove" onclick="removeUserForm(this)" title="Eliminar usuario">×</button>
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
                        <label for="codigo_transportista">Código de Transportista:</label>
                        <select name="users[${userFormCount}][codigo_transportista]" class="form-control select2-transportista" data-placeholder="Buscar transportista por código o nombre...">
                            <option value="">Seleccionar transportista (opcional)</option>
                            <?php foreach ($transportistas as $transportista): ?>
                                <?php 
                                $codigo = $transportistaDataAccess->valueForKey('identificador', $transportista);
                                $nombre = $transportistaDataAccess->valueForKey('nombre', $transportista);
                                ?>
                                <option value="<?php echo htmlspecialchars($codigo); ?>">
                                    <?php echo htmlspecialchars($codigo . ' - ' . $nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="roles-header">
                            <div class="roles-title">Roles:</div>
                            <input type="text" 
                                   class="roles-search" 
                                   placeholder="Buscar roles..."
                                   oninput="filterRoles(this, ${userFormCount})">
                        </div>
                        <div class="roles-container">
                        <?php foreach ($roles as $role): ?>
                                <div class="role-item" data-role-name="<?php echo htmlspecialchars(strtolower($role['name'])); ?>">
                                    <input type="checkbox" 
                                           id="role_${userFormCount}_<?php echo htmlspecialchars($role['id']); ?>"
                                           name="users[${userFormCount}][role_ids][]" 
                                           value="<?php echo htmlspecialchars($role['id']); ?>">
                                    <label for="role_${userFormCount}_<?php echo htmlspecialchars($role['id']); ?>">
                                <?php echo htmlspecialchars($role['name']); ?>
                                    </label>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                `;
                userFormsContainer.appendChild(newUserForm);
                
                // Inicializar Select2 en el nuevo select de transportista
                const newSelect = newUserForm.querySelector('.select2-transportista');
                if (newSelect) {
                    $(newSelect).select2({
                        placeholder: 'Buscar transportista por código o nombre...',
                        allowClear: false,
                        width: '100%',
                        language: {
                            noResults: function() {
                                return "No se encontraron transportistas";
                            },
                            searching: function() {
                                return "Buscando...";
                            }
                        },
                        matcher: function(params, data) {
                            // Si no hay término de búsqueda, mostrar la opción
                            if ($.trim(params.term) === '') {
                                return data;
                            }

                            // No mostrar la opción si no hay texto
                            if (typeof data.text === 'undefined') {
                                return null;
                            }

                            // Búsqueda case-insensitive y con acentos
                            const normalizedTerm = params.term.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                            const normalizedText = data.text.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

                            // Si el texto contiene el término de búsqueda, mostrar la opción
                            if (normalizedText.indexOf(normalizedTerm) > -1) {
                                return data;
                            }

                            // Si no hay coincidencia, no mostrar la opción
                            return null;
                        }
                    });
                }
                
                userFormCount++;
            }

            function removeUserForm(button) {
                const userForm = button.closest('.user-form');
                userForm.remove();
            }

            function filterRoles(input, formIndex) {
                const searchTerm = input.value.toLowerCase();
                const container = input.closest('.form-group').querySelector('.roles-container');
                const roleItems = container.querySelectorAll('.role-item');

                roleItems.forEach(item => {
                    const roleName = item.getAttribute('data-role-name');
                    if (roleName.includes(searchTerm)) {
                        item.classList.remove('hidden');
                    } else {
                        item.classList.add('hidden');
                    }
                });
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
                            // Add new form
                            addUserForm();
                            
                            // Get the newly created form (it will be the last one)
                            const forms = userFormsContainer.querySelectorAll('.user-form');
                            const currentForm = forms[forms.length - 1];
                            
                            // Fill the form fields
                            currentForm.querySelector(`input[name="users[${userFormCount - 1}][cedula]"]`).value = row[0] || '';
                            currentForm.querySelector(`input[name="users[${userFormCount - 1}][nombres]"]`).value = row[1] || '';
                            currentForm.querySelector(`input[name="users[${userFormCount - 1}][apellidos]"]`).value = row[2] || '';
                            currentForm.querySelector(`input[name="users[${userFormCount - 1}][email]"]`).value = row[3] || '';
                            currentForm.querySelector(`input[name="users[${userFormCount - 1}][password]"]`).value = row[4] || '';
                            
                            // Handle roles if they exist
                            if (row[5]) {
                                const roleIds = row[5].toString().split(',').map(id => id.trim());
                            roleIds.forEach(roleId => {
                                    const checkbox = currentForm.querySelector(`input[name="users[${userFormCount - 1}][role_ids][]"][value="${roleId}"]`);
                                if (checkbox) {
                                    checkbox.checked = true;
                                }
                            });
                            }
                            
                            // Handle código de transportista if it exists
                            if (row[6]) {
                                const codigoTransportista = row[6].toString().trim();
                                const selectTransportista = currentForm.querySelector(`select[name="users[${userFormCount - 1}][codigo_transportista]"]`);
                                if (selectTransportista) {
                                    // Buscar la opción que coincida con el código
                                    for (let option of selectTransportista.options) {
                                        if (option.value === codigoTransportista) {
                                            option.selected = true;
                                            // Actualizar Select2 si está inicializado
                                            if ($(selectTransportista).hasClass('select2-hidden-accessible')) {
                                                $(selectTransportista).trigger('change');
                                            }
                                            break;
                                        }
                                    }
                                }
                            }
                        });
                        
                        // Reset userFormCount to the correct value
                        userFormCount = json.length - 1;
                    };
                    reader.readAsArrayBuffer(file);
                }
            });

            // --- NUEVO: Reindexar formularios antes de enviar ---
            document.getElementById('userForm').addEventListener('submit', function(e) {
                e.preventDefault(); // Prevenir el envío inmediato
                const userFormsContainer = document.getElementById('userFormsContainer');
                const forms = userFormsContainer.querySelectorAll('.user-form');
                forms.forEach((form, idx) => {
                    // Reindexar los campos de cada formulario
                    form.querySelectorAll('input, select, label').forEach(el => {
                        if (el.name) {
                            el.name = el.name.replace(/users\[\d+\]/g, `users[${idx}]`);
                        }
                        if (el.id) {
                            el.id = el.id.replace(/role_\d+_/g, `role_${idx}_`);
                        }
                        if (el.htmlFor) {
                            el.htmlFor = el.htmlFor.replace(/role_\d+_/g, `role_${idx}_`);
                        }
                        if (el.getAttribute('oninput')) {
                            el.setAttribute('oninput', `filterRoles(this, ${idx})`);
                        }
                    });
                    
                    // Manejar Select2 si existe
                    const select2Element = form.querySelector('.select2-transportista');
                    if (select2Element && $(select2Element).hasClass('select2-hidden-accessible')) {
                        // Destruir y recrear Select2 para el nuevo índice
                        $(select2Element).select2('destroy');
                        $(select2Element).select2({
                            placeholder: 'Buscar transportista por código o nombre...',
                            allowClear: false,
                            width: '100%',
                            language: {
                                noResults: function() {
                                    return "No se encontraron transportistas";
                                },
                                searching: function() {
                                    return "Buscando...";
                                }
                            },
                            matcher: function(params, data) {
                                // Si no hay término de búsqueda, mostrar la opción
                                if ($.trim(params.term) === '') {
                                    return data;
                                }

                                // No mostrar la opción si no hay texto
                                if (typeof data.text === 'undefined') {
                                    return null;
                                }

                                // Búsqueda case-insensitive y con acentos
                                const normalizedTerm = params.term.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                                const normalizedText = data.text.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

                                // Si el texto contiene el término de búsqueda, mostrar la opción
                                if (normalizedText.indexOf(normalizedTerm) > -1) {
                                    return data;
                                }

                                // Si no hay coincidencia, no mostrar la opción
                                return null;
                            }
                        });
                    }
                });
                // Enviar el formulario manualmente solo con los formularios presentes
                this.submit();
            });
        </script>
    
        <?php return ob_get_clean();
    }
}