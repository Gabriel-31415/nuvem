<p><b>Id: </b>{{ $machine->id }}</p>
<p><b>Hash: </b>{{ $machine->hashcode }}</p>
<p><b>CPU Limite: </b>{{ $machine->cpu_utilizavel }}%</p>
<p><b>RAM Limite: </b>{{ $machine->ram_utilizavel }} MB</p>
<p><b>In Activity: </b>{{ $machine->disponivel?'Yes':'No' }}</p>
<p><b>Created At: </b>{{ $machine->created_at }}</p>
<p><b>Updated At: </b>{{ $machine->updated_at }}</p>