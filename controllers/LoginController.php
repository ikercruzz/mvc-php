<?php

namespace Controllers;

use Classes\Email;
use Model\Usuario;
use MVC\Router;

class LoginController{
    public static function login(Router $router) {
        $alertas = [];

        if($_SERVER['REQUEST_METHOD']  === 'POST'){

            // echo 'Enviaste el formulario';
            $auth = new Usuario($_POST); //$_POST guardara todos los datos del formulario 
            $alertas = $auth->validarLogin();

            if(empty($alertas)){
                // echo 'El usuario proporciono correo y contraseña';
                $usuario = Usuario::buscarPorCampo('email', $auth->email);
                // debuguear($usuario);
                if($usuario){
                    //comprobar contraseña
                    if($usuario->comprobarContrasenaAndVerificado($auth->password)){
                        
                    //autenticar usuario 
                        session_start();
                        $_SESSION['id'] = $usuario->id;

                        $_SESSION['nombre'] = $usuario->nombre . ' ' . $usuario->apellido;
                        $_SESSION['email'] = $usuario->email;
                        $_SESSION['login'] = true;

                        // debuguear($_SESSION);

                        //Rediccionamiento
                        if($usuario->admin == 1){
                            $_SESSION['admin'] = $usuario->admin ?? null;
                            header('Location: /admin');
                        }  else {
                            header('Location: /cliente');
                        }

                    }
                    }else{
                    Usuario::setAlerta('error', 'Usuario no encontrado');
                }
            }
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/login',[
            'alertas' => $alertas
        ]);
    }

    public static function logout() {
        echo 'Desde logout';
    }


    public static function olvide(Router $router) {
        $alertas = [];

        if($_SERVER['REQUEST_METHOD']  === 'POST'){
            
            $auth = new Usuario($_POST);
            $alertas = $auth->validarEmail();

            if(empty($alertas)){
                $usuario = Usuario::buscarPorCampo('email', $auth->email);
                // debuguear($usuario);

                if($usuario && $usuario->confirmado == 1){
                    $usuario->crearToken();
                    $usuario->guardar();

                    $email = new Email(
                        $usuario->email,
                        $usuario->nombre,
                        $usuario->token
                    );

                    $email->enviarInstrucciones();

                    Usuario::setAlerta('exito', 'Revisa tu correo');
                } else {
                    Usuario::setAlerta('error', 'El usuario no existe o no esta confirmado');
                }
            }
        }
        $alertas = Usuario::getAlertas();

        $router->render('auth/olvide-password', [
            'alertas' => $alertas
        ]);
    }



    public static function recuperar(Router $router) {
        $alertas = [];

        $error = false;

        $token = s($_GET['token']);
         //  debuguear($token);

        $usuario = Usuario::buscarPorCampo('token', $token);

            //    debuguear($usuario);

          if(empty($usuario)){
             Usuario::setAlerta('error', 'Token no valido');
             $error = true;
          }

          if($_SERVER['REQUEST_METHOD'] === 'POST'){

            $password = new Usuario($_POST);
            $alertas = $password->validarPassword();

            if(empty($alertas)){
                $usuario->password = null;

                $usuario->password = $password->password;
                $usuario->hashPassword();
                $usuario->token = null;

                $resultado = $usuario->guardar();
                if($resultado){
                    header('Location: /');
                }
            }

          }

          $alertas = Usuario::getAlertas();

        $router->render('auth/recuperar-password', [
            'alertas' => $alertas,
            'error' => $error
        ]);
    }


    public static function crear(Router $router) {
        
        $usuario = new Usuario;

        $alertas = [];
        
        if($_SERVER['REQUEST_METHOD']  === 'POST'){

            $usuario->sincronizar($_POST); //Metodo sincronizar 
            $alertas = $usuario->validarNuevaCuenta();


            //revismaos que alertas este vacío 
            if(empty($alertas)){

                //Verificar en la BD si el usuario no este repetido o no exista 
                $resultado = $usuario->existeUsuario();

                if($resultado->num_rows) {
                    $alertas = Usuario::getAlertas(); //Con los dos puntos llamamos una funcion estatica 
                } else {
                    //Hashear el password con la variable usuario
                    //hashPassword nos ayuda a ocultar la contraseña y no ponerlo en texto plano 
                    $usuario->hashPassword();
                    //Generar un token unico 
                    $usuario->crearToken();
                    //enviar el email con clase de ayuda para enviar, correo, nombre y Token
                    $email = new Email($usuario->email, $usuario->nombre, $usuario->token);
                    $email->enviarConfirmacion();

                    //Creación del usuariO
                    $resultado = $usuario->guardar();
                    
                    // debuguear($usuario);

                    if($resultado){
                        header('Location: /envio-mensaje');
                    }
                }
            }

        }
        $router->render('auth/crear-cuenta',[
            'usuario' => $usuario,
            'alertas' => $alertas
        ]);
    }

    public static function confirmar(Router $router) {
        $alertas = [];
        $token = s($_GET['token']); //La S sanitiza el token al momento de extraerlo 
        
        // debuguear($token);
        $usuario = Usuario::buscarPorCampo('token', $token);

        if(empty($usuario)) {
            echo 'Token no valido';
            Usuario::setAlerta('error', 'Token no valido');
        }else {
            // echo 'Token valido, confirmando usuario...';
            
            $usuario->confirmado = '1';
            $usuario->token = '';
            // debuguear($usuario);


            //obtener las alertas 
            $usuario->guardar();
            Usuario::setAlerta('exito', 'Cuenta comprobada');
        }


        $alertas= Usuario::getAlertas();
        //BUSCAREMOS EL TOKEN EN LA BASE DE DATOS
        // Usuario::buscarPorCampo('token', $token);
        $router->render('auth/confirmar-cuenta', [
            'alertas' => $alertas
        ]);
    }

    public static function mensaje(Router $router) {
        $router->render('auth/mensaje');
    }
    public static function admin(){
        echo 'Desde admin...';
    }

    public static function cliente(){
        echo 'Desde cliente...';
    }
}