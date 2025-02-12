<?php

class CreateUserHTMLPage extends GTKHTMLPage
{
   

    public function processPost()
    {
        $cedula = $_POST['cedula'];
        $nombres = $_POST['nombres'];
        $apellidos = $_POST['apellidos'];
        $email = $_POST['email'];
        $password = $_POST['password'];

        $user = [
            'cedula' => $cedula,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'email' => $email,
            'password' => $password
        ];

        $personaDataAccess = DataAccessManager::get('persona');

        $result = $personaDataAccess->createUserIfNotExists($user);

        if ($result) {
            $this->messages[] = 'Usuario creado exitosamente.';
        } else {
            $this->messages[] = 'Error al crear el usuario o el usuario ya existe.';
        }
    }

    public function renderMessages()
    {
        $toReturn = "";

        if (count($this->messages) > 0)
        {
            $toReturn .= "<h1 class='font-bold'>Mensajes</h1>";
            $toReturn .= "<div>";
            foreach ($this->messages as $message)
            {
                $toReturn .= "<div>";
                if (is_string($message))
                {
                    $toReturn .= htmlspecialchars($message);
                }
                else
                {
                    $toReturn .= print_r($message, true);
                }
                $toReturn .= "</div>";
            }
            $toReturn .= "</div>";
        }

        return $toReturn;
    }

    public function renderBody()
    {
        ob_start(); ?>
    
        <h1>Crear Nuevo Usuario</h1>

        <?php
        echo $this->renderMessages();
        ?>

        <form action="/auth/create_user_handler.php" method="post">
            <label for="cedula">Cédula:</label>
            <input type="text" id="cedula" name="cedula" required class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            <br>
            <label for="nombres">Nombres:</label>
            <input type="text" id="nombres" name="nombres" required class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            <br>
            <label for="apellidos">Apellidos:</label>
            <input type="text" id="apellidos" name="apellidos" required class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            <br>
            <label for="email">Correo Electrónico:</label>
            <input type="email" id="email" name="email" required class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            <br>
            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            <br>
            <br>
            <input type="submit" value="Crear Usuario" class="px-8 w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 rounded">
        </form>
    
        <?php return ob_get_clean();
    }
}
?>