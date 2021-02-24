<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Image;
use App\Models\Container;
use App\Models\Maquina;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Exception;

class BasicController extends Controller
{
    

    // public function listImages()
    // {
    //     $url = env('DOCKER_HOST');
    //     $images = Image::all();
    //     // $images = Http::get("$url/images/json");
    //     // $images = json_decode($images, true);
    //     // dd($images[0]['RepoTags'][0]);
    //     // $sites = [
    //     //     'images' => Image::all(),            
                       
    //     // ];
        
    //     return view('pages.student.basic.index', compact('images'));
    // }

    public function index()
    {
    	$images = Image::all();
    	try {
            $url = env('DOCKER_HOST');            
            $info = Http::get("$url/system/df");
        } catch (Exception $e) {
            return  $e->getMessage();
        }

        $url = env('DOCKER_HOST');
        // $imagesDocker = Image::all();
        $imagesDocker = Http::get("$url/images/json");
        $imagesDocker = json_decode($imagesDocker, true);
        // dd($imagesDocker[0]['RepoTags']);
        
    	$params = [
            'mycontainers' => Container::where('user_id', Auth::user()->id)->paginate(10),
            'dockerHost' => env('DOCKER_HOST'),
            'title' => 'My Containers',
            'info'  => $info->json()['Containers']
            
        ];

        $socketParams = json_encode([
                'dockerHost' => env('DOCKER_HOST_WS'),
                'container_id' => null,
            ]);

    	return view('pages.student.basic.index', compact('params','images','imagesDocker', 'socketParams'));
    }

    public function containers()
    {
    	try {
            $url = env('DOCKER_HOST');            
            $info = Http::get("$url/containers/json");
        } catch (Exception $e) {
            return  $e->getMessage();
        }
        $params = [
            'mycontainers' => $info->json(),
            'dockerHost' => env('DOCKER_HOST'),
            'title' => 'My Containers',
        ];

        //dd($params['mycontainers']);	

        return view('pages.student.basic.containers', $params);
    }

    public function addContainer()
    {
        $container = Container::firstWhere('docker_id', $id);

        $params = [
            'mycontainer' => $container,
            'socketParams' => json_encode([
                'dockerHost' => env('DOCKER_HOST_WS'),
                'container_id' => $id,
            ]),
        ];

        return redirect()->route('aluno.basic.index', ['params' => $params]);
    }

    public function containerStore(Request $request)
    {   
        $user = Auth()->user();
        try {
            
            // if ($user->containers()->count() > $user->containers) {
            //     return redirect()->route('aluno.basic.index')->with('error', 'Quantidade de máxima containers criados atingido!');
            // }
            $url = env('DOCKER_HOST');
            $data = $this->setDefaultDockerParams($request->all());
            // dd($data);
            $this->pullImage($url, Image::find($data['image_id']));
            $this->createContainer($url, $data);
            
            return redirect()->route('aluno.basic.containers');
            // return redirect()->route('aluno.basic.index')->with('success', 'Container creation is running!');
        } catch (Exception $e) {
            return  $e->getMessage();
        }
    }

    private function setDefaultDockerParams(array $data)
    {
        $image = Image::find($data['image_id']);
        $data['Image'] = $image->fromImage .':'.$image->tag;
        $data['Memory'] = $data['Memory'] ? intval($data['Memory']) : 0;
        $inicias = "jean";

        $data['Env'] = $data['envVariables'] ? explode(';', $data['envVariables']) : [];
        array_pop($data['Env']); // Para remover string vazia no ultimo item do array, evitando erro na criação do container.

        $data['AttachStdin'] = true;
        $data['AttachStdout'] = true;
        $data['AttachStderr'] = true;
        $data['OpenStdin'] = true;
        $data['StdinOnce'] = false;
        $data['Tty'] = true;
        
        $data['ExposedPorts'] = json_decode('{"80/tcp": { }}');

        // $data['Shell'] = [
        //     '/bin/bash'
        // ];
        //  $data['Shell'] = [
        //     'service;apache2;start;service;mysql;start;'
        // ];
        $data['Entrypoint'] = [
            "/script.sh", "{$inicias}"
        ];
        // $data['Cmd'] = [
        //     "chmod", "+x", "script.sh"
        // ];
        // $data['Cmd'] = [
        //     'service;apache2;start;service;mysql;start;'
        // ];
        // $data['Cmd'] = [
        //     'service', 'apache2', 'start', '&&', 'service','mysql','start', 
        // ];
        // $data['Cmd'] = [
        //     'sh', '-c', 'service', 'apache2', 'start', '&&', 'service', 'mysql', 'start'
        // ];
        $data['Cmd'] = [
            ''
        ];
        // $data['Cmd'] = [
        //     "", "{$inicias}"
        // ];


        $data['HostConfig'] = [
            'PublishAllPorts' => true,
            'Privileged' => true,
            'RestartPolicy' => [
                'name' => 'always',
            ],
            'Binds' => [
                '/var/run/docker.sock:/var/run/docker.sock',
                '/tmp:/tmp',
             ],
             'PortBindings' => json_decode('{"80/tcp": [{"HostPort":"8080"}]}')
             
        ];
        // dd($data);
        
        return $data;
    }

    private function pullImage($url, Image $image)
    {
        $uri = "images/create?fromImage=$image->fromImage&tag=$image->tag";
        $image->fromSrc ? $uri .= "&fromSrc=$image->fromSrc" : $uri;
        $image->repo ? $uri .= "&repo=$image->repo" : $uri;
        $image->message ? $uri .= "&message=$image->message" : $uri;

        $response = Http::post("$url/$uri");
        
        if ($response->getStatusCode() != 200) {
            dd($response->json());
        }
    }

    private function createContainer($url, $data)
    {
        
        $response = Http::asJson()->post("$url/containers/create", $data);        
        $exec = $this->exec();
        if ($response->getStatusCode() == 201) {
            $container_id = $response->json()['Id'];
            $response = Http::asJson()->post("$url/containers/$container_id/start");
            $response = Http::asJson()->post("$url/containers/$container_id/exec", $exec);
            // dd($response);
            
            $data['hashcode_maquina'] = Maquina::first()->hashcode;
            $data['docker_id'] = $container_id;
            $data['dataHora_instanciado'] = now();
            $data['dataHora_finalizado'] = $response->getStatusCode() == 204 ? null : now();

            Container::create($data);
        } else {
            dd($response->json());
        }
    }

    private function exec()
    {
        $data['AttachStdin'] = true;
        $data['AttachStdout'] = true;
        $data['AttachStderr'] = true;
        $data['Tty'] = true;

        $data['Entrypoint'] = [
           '/bin/bash', 
        ];
        $data['Cmd'] = [
            'service', 'apache2','start', 'service', 'mysql','start',
        ];
        // $data['Cmd'] = [
        //     './script.sh'
        // ];

        
        // dd($data);
        
        return $data;
    }

    public function playStop($container_id)
    {
        $instancia = Container::where('docker_id', $container_id)->first();
        $url = env('DOCKER_HOST');

        if ($instancia->dataHora_finalizado) {
            $host = "$url/containers/$container_id/start";
            $dataHora_fim = null;
        } else {
            $host = "$url/containers/$container_id/stop";
            $dataHora_fim = now();
        }

        try {
            Http::post($host);

            $instancia->dataHora_finalizado = $dataHora_fim;
            $instancia->save();

            return redirect()->route('aluno.basic.index')->with('success', 'Container created with sucess!');
        } catch (Exception $e) {
            return redirect()->route('aluno.basic.index')->with('error', "Fail to stop the container! $e");
        }
    }

    public function destroy($id)
    {
        $url = env('DOCKER_HOST');

        $responseStop = Http::post("$url/containers/$id/stop");
        if ($responseStop->getStatusCode() == 204 || $responseStop->getStatusCode() == 304) {
            $responseDelete = Http::delete("$url/containers/$id");
            if ($responseDelete->getStatusCode() == 204) {
                $instancia = Container::firstWhere('docker_id', $id);
                $instancia->delete();

                return redirect()->route('aluno.basic.index')->with('success', 'Container deleted with sucess!');
            } else {
                dd($responseDelete->json());

                return redirect()->route('aluno.basic.index')->with('error', 'Fail, Container not delete!');
            }
        } else {
            dd($responseStop->json());
        }
    }

}