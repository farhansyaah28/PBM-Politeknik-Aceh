<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Paginator;
use PDO;
use RuntimeException;

final class ParticipantManagementService
{
    private const CSV_HEADERS = ['full_name', 'email', 'phone_number', 'school_name', 'graduation_year', 'admission_path', 'program_code', 'wave_code'];
    /** @var list<string>|null */
    private ?array $activeAdmissionPathNames = null;

    public function __construct(private PDO $db, private FileImportService $files, private string $credentialEncryptionKey = '') {}

    /** @param array<string,string> $input @return array{number:string,password:string} */
    public function create(array $input, string $actorId): array
    {
        $data = $this->validate($input);
        $this->db->beginTransaction();
        try {
            $this->activeAdmin($actorId);
            $program = $this->catalogValue('study_programs', $data['program_id']);
            $wave = $this->catalogValue('admission_waves', $data['wave_id']);
            $number = $this->newParticipantNumber();
            $password = $this->newPassword();
            $passwordCiphertext = $this->encryptCredential($password);
            if ($passwordCiphertext === null) throw new ValidationException(['password' => 'Kunci enkripsi kredensial peserta belum dikonfigurasi.']);
            $userId = $this->uuid(); $participantId = $this->uuid();
            $username = $this->newUsername($data['username'] ?: strtolower(str_replace('-', '', $number)));
            $this->db->prepare('INSERT INTO users (id,username,email,password_hash,role,status,created_at,updated_at) VALUES (:id,:username,:email,:password_hash,"PARTICIPANT","ACTIVE",UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute(['id'=>$userId,'username'=>$username,'email'=>$data['email'],'password_hash'=>password_hash($password, PASSWORD_DEFAULT)]);
            $this->db->prepare('INSERT INTO participants (id,user_id,registration_number,password_ciphertext,full_name,email,phone_number,school_name,graduation_year,admission_path,program_choice,wave,program_id,wave_id,account_status,verification_status,registration_submitted_at,created_at,updated_at) VALUES (:id,:user_id,:number,:password_ciphertext,:full_name,:email,:phone,:school,:year,:admission_path,:program_name,:wave_name,:program_id,:wave_id,"ACTIVE","APPROVED",UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([
                'id'=>$participantId,'user_id'=>$userId,'number'=>$number,'password_ciphertext'=>$passwordCiphertext,'full_name'=>$data['full_name'],'email'=>$data['email'],'phone'=>$data['phone_number'],'school'=>$data['school_name'],'year'=>$data['graduation_year'],'admission_path'=>$data['admission_path'],'program_name'=>$program['name'],'wave_name'=>$wave['name'],'program_id'=>$program['id'],'wave_id'=>$wave['id'],
            ]);
            $this->db->prepare('INSERT INTO verification_decisions (id,participant_id,decision,checklist_json,note,reviewed_by,created_at) VALUES (:id,:participant,"APPROVED",:checks,:note,:actor,UTC_TIMESTAMP())')->execute([
                'id' => $this->uuid(),
                'participant' => $participantId,
                'checks' => json_encode(['V-01','V-02','V-03','V-04','V-05','V-06','V-07','V-08','V-09','V-10'], JSON_THROW_ON_ERROR),
                'note' => 'Terdaftar dan diverifikasi otomatis oleh Admin.',
                'actor' => $actorId,
            ]);
            $this->audit($actorId, $participantId, 'participant.created', 'participant', $participantId);
            $this->db->commit();
            return ['number'=>$number,'password'=>$password];
        } catch (\Throwable $exception) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $exception; }
    }

    /** @param array<string,mixed> $file @return array{created:int,errors:list<string>,credentials:list<array{number:string,password:string,name:string}>} */
    public function import(array $file, string $actorId): array
    {
        $rows = $this->files->parseXlsxUpload($file, self::CSV_HEADERS);
        $programs = $this->db->query('SELECT code,id FROM study_programs WHERE is_active=1')->fetchAll(PDO::FETCH_KEY_PAIR);
        $waves = $this->db->query('SELECT code,id FROM admission_waves WHERE is_active=1')->fetchAll(PDO::FETCH_KEY_PAIR);
        $result = ['created'=>0,'errors'=>[],'credentials'=>[]];
        foreach ($rows as $row) {
            $line = $row['_line'];
            $program = $programs[strtoupper($row['program_code'])] ?? null;
            $wave = $waves[strtoupper($row['wave_code'])] ?? null;
            if (!$program || !$wave) { $result['errors'][] = "Baris {$line}: Kode Program Studi atau Kode Gelombang tidak aktif."; continue; }
            try {
                $created = $this->create([
                    'full_name'=>$row['full_name'],'email'=>$row['email'],'phone_number'=>$row['phone_number'],'school_name'=>$row['school_name'],'graduation_year'=>$row['graduation_year'],'admission_path'=>$row['admission_path'],'program_id'=>(string)$program,'wave_id'=>(string)$wave,'username'=>'',
                ], $actorId);
                $result['created']++;
                $result['credentials'][] = ['number'=>$created['number'],'password'=>$created['password'],'name'=>$row['full_name']];
            } catch (ValidationException $exception) { $result['errors'][] = "Baris {$line}: " . implode(' ', $exception->errors); }
            catch (\Throwable) { $result['errors'][] = "Baris {$line}: data tidak dapat dibuat."; }
        }
        return $result;
    }

    /** @param array{search?:string,status?:string,admission_path?:string,page?:mixed} $filters @return array{participants:list<array<string,mixed>>,admissionPaths:list<string>,programs:list<array<string,mixed>>,waves:list<array<string,mixed>>,paginator:Paginator} */
    public function data(array $filters): array
    {
        $where=[]; $params=[]; $search=trim($filters['search'] ?? ''); $status=strtoupper(trim($filters['status'] ?? 'ALL'));
        $admissionPath=trim($filters['admission_path'] ?? '');
        if ($search !== '') {
            $where[]='(p.registration_number LIKE :search_number OR p.full_name LIKE :search_name OR p.email LIKE :search_email)';
            $params['search_number']='%'.$search.'%'; $params['search_name']='%'.$search.'%'; $params['search_email']='%'.$search.'%';
        }
        if (in_array($status,['PENDING','APPROVED','NEEDS_CORRECTION','REJECTED'],true)) { $where[]='p.verification_status=:status'; $params['status']=$status; }
        if ($admissionPath !== '' && $admissionPath !== 'ALL') {
            $where[]='p.admission_path=:admission_path';
            $params['admission_path']=$admissionPath;
        }
        $whereSql = $where === [] ? '' : ' WHERE '.implode(' AND ',$where);
        $count=$this->db->prepare('SELECT COUNT(*) FROM participants p'.$whereSql);$count->execute($params);
        $paginator=Paginator::fromRequest((int)$count->fetchColumn(),$filters['page']??1);
        $statement=$this->db->prepare('SELECT p.id,p.registration_number,p.full_name,p.email,p.phone_number,p.school_name,p.graduation_year,p.admission_path,p.program_choice,p.wave,p.verification_status,p.created_at FROM participants p'.$whereSql.' ORDER BY p.created_at DESC LIMIT '.$paginator->perPage.' OFFSET '.$paginator->offset());
        $statement->execute($params);
        return ['participants'=>$statement->fetchAll(),'admissionPaths'=>$this->activeAdmissionPaths(),'programs'=>$this->db->query('SELECT id,code,name FROM study_programs WHERE is_active=1 ORDER BY name')->fetchAll(),'waves'=>$this->db->query('SELECT id,code,name FROM admission_waves WHERE is_active=1 ORDER BY name')->fetchAll(),'paginator'=>$paginator];
    }

    /** @return array{number:string,password:string} */
    public function passwordForAdmin(string $participantId, string $adminId): array
    {
        $this->activeAdmin($adminId);
        $statement = $this->db->prepare('SELECT id,registration_number,password_ciphertext FROM participants WHERE id=:id LIMIT 1');
        $statement->execute(['id'=>$participantId]);
        $participant = $statement->fetch();
        if (!$participant) throw new ValidationException(['participant'=>'Peserta tidak ditemukan.']);
        $password = $this->decryptCredential($participant['password_ciphertext'] ?? null);
        if ($password === null) throw new ValidationException(['password'=>'Password peserta lama tidak tersedia untuk dilihat ulang. Buat prosedur reset password untuk peserta ini.']);
        $this->audit($adminId, $participantId, 'participant.password_revealed', 'participant', $participantId);
        return ['number'=>(string)$participant['registration_number'],'password'=>$password];
    }

    public static function templateRows(): array { return [self::CSV_HEADERS, ['Ayu Salsabila', 'ayu@example.test', '08123456789', 'SMA Negeri 1', '2026', 'Reguler', 'TI', 'GEL-1-2026']]; }
    /** @return list<string> */
    public function activeAdmissionPaths(): array
    {
        if ($this->activeAdmissionPathNames === null) {
            $this->activeAdmissionPathNames = array_map(
                static fn (array $row): string => (string) $row['name'],
                $this->db->query('SELECT name FROM admission_paths WHERE is_active = 1 ORDER BY name')->fetchAll(),
            );
        }
        return $this->activeAdmissionPathNames;
    }
    /** @param array<string,string> $input @return array<string,string> */
    private function validate(array $input): array
    {
        $data=[]; foreach(['full_name','email','phone_number','school_name','graduation_year','admission_path','program_id','wave_id','username'] as $key) $data[$key]=trim($input[$key]??'');
        $errors=[];
        foreach(['full_name','email','phone_number','school_name','graduation_year','admission_path','program_id','wave_id'] as $key) if($data[$key]==='')$errors[$key]='Wajib diisi.';
        foreach(['full_name'=>160,'email'=>190,'phone_number'=>32,'school_name'=>190] as $key=>$limit) if(mb_strlen($data[$key])>$limit)$errors[$key]="Maksimal {$limit} karakter.";
        if($data['email']!==''&&!filter_var($data['email'],FILTER_VALIDATE_EMAIL))$errors['email']='Email tidak valid.';
        if($data['graduation_year']!==''&&(!ctype_digit($data['graduation_year'])||(int)$data['graduation_year']<1950||(int)$data['graduation_year']>2100))$errors['graduation_year']='Tahun lulus tidak valid.';
        if($data['admission_path']!==''&&!in_array($data['admission_path'],$this->activeAdmissionPaths(),true))$errors['admission_path']='Jalur masuk tidak aktif atau tidak ditemukan.';
        if($data['username']!==''&&!preg_match('/^[a-z0-9._-]{4,32}$/i',$data['username']))$errors['username']='Username 4-32 karakter hanya huruf, angka, titik, garis bawah, atau tanda hubung.';
        if($errors!==[])throw new ValidationException($errors);
        return $data;
    }
    /** @return array{id:string,name:string} */ private function catalogValue(string $table,string $id):array {$statement=$this->db->prepare("SELECT id,name FROM {$table} WHERE id=:id AND is_active=1 LIMIT 1");$statement->execute(['id'=>$id]);$row=$statement->fetch();if(!$row)throw new ValidationException(['catalog'=>'Program studi atau gelombang aktif tidak ditemukan.']);return $row;}
    private function activeAdmin(string $id):void{$statement=$this->db->prepare('SELECT id FROM users WHERE id=:id AND role="ADMIN" AND status="ACTIVE" LIMIT 1');$statement->execute(['id'=>$id]);if(!$statement->fetch())throw new ValidationException(['auth'=>'Hanya Admin aktif yang dapat mengelola peserta.']);}
    private function newParticipantNumber():string { do {$number='PES-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(3)));$check=$this->db->prepare('SELECT id FROM participants WHERE registration_number=:number');$check->execute(['number'=>$number]);}while($check->fetchColumn());return $number;}
    private function newUsername(string $value):string { $base=substr(strtolower(preg_replace('/[^a-z0-9._-]/i','',$value)??''),0,32);if($base==='')$base='peserta';$candidate=$base;$index=2;while(true){$q=$this->db->prepare('SELECT id FROM users WHERE username=:username');$q->execute(['username'=>$candidate]);if(!$q->fetchColumn())return $candidate;$suffix='-'.$index++;$candidate=substr($base,0,32-strlen($suffix)).$suffix;}}
    private function newPassword():string{return substr(strtr(base64_encode(random_bytes(12)),'+/','AZ'),0,12).'!';}
    private function encryptCredential(string $password): ?string
    {
        if ($this->credentialEncryptionKey === '' || !function_exists('openssl_encrypt')) return null;
        $iv=random_bytes(12);$tag='';
        $key=hash('sha256','participant-password:v1:'.$this->credentialEncryptionKey,true);
        $ciphertext=openssl_encrypt($password,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
        if($ciphertext===false)throw new RuntimeException('Password peserta belum dapat dienkripsi.');
        return base64_encode($iv.$tag.$ciphertext);
    }
    private function decryptCredential(mixed $payload): ?string
    {
        if(!is_string($payload)||$payload===''||$this->credentialEncryptionKey===''||!function_exists('openssl_decrypt'))return null;
        $decoded=base64_decode($payload,true);if($decoded===false||strlen($decoded)<29)return null;
        $key=hash('sha256','participant-password:v1:'.$this->credentialEncryptionKey,true);
        $password=openssl_decrypt(substr($decoded,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($decoded,0,12),substr($decoded,12,16));
        return $password===false?null:$password;
    }
    private function audit(string $actor,string $participant,string $action,string $type,string $target):void{$this->db->prepare('INSERT INTO audit_logs (id,actor_type,actor_id,participant_id,action,target_type,target_id,request_id,created_at) VALUES (:id,"ADMIN",:actor,:participant,:action,:type,:target,:request,UTC_TIMESTAMP())')->execute(['id'=>$this->uuid(),'actor'=>$actor,'participant'=>$participant,'action'=>$action,'type'=>$type,'target'=>$target,'request'=>bin2hex(random_bytes(12))]);}
    private function uuid():string{$bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&15)|64);$bytes[8]=chr((ord($bytes[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($bytes),4));}
}
