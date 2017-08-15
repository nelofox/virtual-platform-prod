<?php
namespace Muserpol\Http\Controllers\EconomicComplement;
use Illuminate\Http\Request;

use Muserpol\Http\Requests;
use Muserpol\Http\Controllers\Controller;
use Illuminate\Contracts\Filesystem\Factory;
use Storage;
use File;
use Log;
use DB;
use Auth;
use Session;
use Carbon\Carbon;
use Muserpol\Helper\Util;
use Maatwebsite\Excel\Facades\Excel;

use Muserpol\Affiliate;
use Muserpol\EconomicComplement;
use Muserpol\EconomicComplementProcedure;
use Muserpol\EconomicComplementApplicant;
use Muserpol\EconomicComplementLegalGuardian;


use App\CustomCollection;

class EconomicComplementImportExportController extends Controller
{

    public function index()
    {

    }

    public static function import_from_senasir(Request $request)
    {
        if($request->hasFile('archive'))
        {
          global $year, $semester, $results,$i,$afi,$list;
          $reader = $request->file('archive');
          $filename = $reader->getRealPath();
          $year = $request->year;
          $semester = $request->semester;
          Excel::load($filename, function($reader) {
                  global $results,$i,$afi,$list;
                  ini_set('memory_limit', '-1');
                  ini_set('max_execution_time', '-1');
                  ini_set('max_input_time', '-1');
                  set_time_limit('-1');
                  $results = collect($reader->get());
          });

        //  return response()->json($results);
          $afi;
          $found=0;
          $nofound=0;
          $procedure = EconomicComplementProcedure::whereYear('year','=',$year)->where('semester','=',$semester)->first();
          foreach ($results as $datos) {
            
            $ext = ($datos->num_com ? "-".$datos->num_com : '');
            $ext = str_replace(' ','', $ext);                                  
            if($datos->renta == "DERECHOHABIENTE"){
              $comp = DB::table('eco_com_applicants') // VIUDEDAD
                  ->select(DB::raw('eco_com_applicants.identity_card as ci_app,economic_complements.*, eco_com_types.id as type'))
                  ->leftJoin('economic_complements','eco_com_applicants.economic_complement_id','=','economic_complements.id')
                  ->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')                                            
                  ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                  ->leftJoin('eco_com_types','eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
                  ->whereRaw("LTRIM(eco_com_applicants.identity_card,'0') ='".rtrim($datos->carnet.''.$ext)."'")
                  ->where('eco_com_types.id','=', 2)
                  ->where('affiliates.pension_entity_id','=', 5)
                  ->whereYear('economic_complements.year', '=', $year)
                  ->where('economic_complements.semester', '=', $semester)->first();
            }
            elseif($datos->renta == "TITULAR")
            {
                $comp = DB::table('eco_com_applicants') // VEJEZ
                  ->select(DB::raw('eco_com_applicants.identity_card as ci_app,economic_complements.*, eco_com_types.id as type'))
                  ->leftJoin('economic_complements','eco_com_applicants.economic_complement_id','=','economic_complements.id')
                  ->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')                                            
                  ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                  ->leftJoin('eco_com_types','eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
                  ->whereRaw("LTRIM(eco_com_applicants.identity_card,'0') ='".rtrim($datos->carnet.''.$ext)."'")                
                  ->where('eco_com_types.id','=', 1)
                  ->where('affiliates.pension_entity_id','=', 5)
                  ->whereYear('economic_complements.year', '=', $year)
                  ->where('economic_complements.semester', '=', $semester)->first();
            }
            else
            {
                $comp = DB::table('eco_com_applicants') // ORFANDAD
                  ->select(DB::raw('eco_com_applicants.identity_card as ci_app,economic_complements.*, eco_com_types.id as type'))
                  ->leftJoin('economic_complements','eco_com_applicants.economic_complement_id','=','economic_complements.id')
                  ->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')                                            
                  ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                  ->leftJoin('eco_com_types','eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
                  ->whereRaw("LTRIM(eco_com_applicants.identity_card,'0') ='".rtrim($datos->carnet.''.$ext)."'")                
                  ->where('eco_com_types.id','=', 3)
                  ->where('affiliates.pension_entity_id','=', 5)
                  ->whereYear('economic_complements.year', '=', $year)
                  ->where('economic_complements.semester', '=', $semester)->first();
            } 
            $procedure = EconomicComplementProcedure::whereYear('year','=',$year)->where('semester','=',$semester)->first();          
            if ($comp && $procedure->indicator > 0)
            {
               
                $ecomplement = EconomicComplement::where('id','=', $comp->id)->first();
                if (is_null($ecomplement->total_rent))
                {
                  $reimbursements = $datos->reintegro_importe_adicional + $datos->reintegro_inc_gestion;
                  $discount = $datos->renta_dignidad + $datos->reintegro_renta_dignidad + $datos->reintegro_importe_adicional + $datos->reintegro_inc_gestion;
                  $total_rent = $datos->total_ganado - $discount;
                  
                  if($comp->type == 1 && $total_rent < $procedure->indicator)  //Vejez Senasir
                  {
                    $ecomplement->eco_com_modality_id = 8;
                  } 
                  elseif ($comp->type == 2 && $total_rent < $procedure->indicator) //Viudedad 
                  {  
                    $ecomplement->eco_com_modality_id = 9;
                  } 
                  elseif($comp->type == 3 && $total_rent < $procedure->indicator) //Orfandad 
                  {  
                      $ecomplement->eco_com_modality_id = 12;
                  }
                  $ecomplement->sub_total_rent = $datos->total_ganado;                
                  $ecomplement->total_rent =  $total_rent;
                  $ecomplement->dignity_pension = $datos->renta_dignidad;
                  $ecomplement->reimbursement = $reimbursements;
                  $ecomplement->rent_type ='Automatico';
                  $ecomplement->save();                
                  $found ++;
                }
                  
            }
            else{
              $nofound ++;
              $i ++;
              $list = $comp;
            }
          }          
              
          Session::flash('message', "Importación Exitosa"." F:".$found." NF:".$nofound);
          return redirect('economic_complement');
        }
        return back();
    }

    public static function import_from_aps(Request $request) {

      if($request->hasFile('archive'))
      {
        global $year, $semester, $results,$i,$afi,$list;
        $reader = $request->file('archive');
        $filename = $reader->getRealPath();
        $year = $request->year;
        $semester = $request->semester;
        Excel::load($filename, function($reader) {
                global $results,$i,$afi,$list;
                ini_set('memory_limit', '-1');
                ini_set('max_execution_time', '-1');
                ini_set('max_input_time', '-1');
                set_time_limit('-1');
                $results = collect($reader->get());
        });

        $afi;
        $found=0;
        $nofound=0; 
        $procedure = EconomicComplementProcedure::whereYear('year','=',$year)->where('semester','=',$semester)->first();    
        foreach ($results as $datos)
        {   
            $nua = ltrim((string)$datos->nrosip_titular, "0");
            $ci = explode("-",ltrim($datos->nro_identificacion, "0"));
            $ci1 = $ci[0];            
            $afi = DB::table('economic_complements')
                  ->select(DB::raw('affiliates.identity_card as ci_afi,economic_complements.*, eco_com_types.id as type'))     
                  ->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')                                 
                  ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                  ->leftJoin('eco_com_types','eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
                  ->whereRaw("split_part(LTRIM(affiliates.identity_card,'0'), '-',1) = '".$ci1."'")
                  ->whereRaw("LTRIM(affiliates.nua::text,'0') ='".$nua."'")        
                  ->where('affiliates.pension_entity_id','!=', 5)
                  ->whereYear('economic_complements.year', '=', $year)
                  ->where('economic_complements.semester', '=', $semester)->first();

            
              if ($afi)
              { $ecomplement = EconomicComplement::where('id','=', $afi->id)->first(); 
                if ((is_null($ecomplement->total_rent) || $ecomplement->total_rent == 0) && $procedure->indicator > 0 )
                {                              
                    $comp1 = 0;
                    $comp2 = 0;
                    $comp3 = 0;
                    if ($datos->total_cc > 0) {
                        $comp1 = 1;
                    }
                    if ($datos->total_fsa > 0) {
                        $comp2 = 1;
                    }
                    if($datos->total_fs > 0) {
                        $comp3 = 1;
                    }
                    $comp = $comp1 + $comp2 + $comp3;
                                                  
                    

                    //Vejez
                    if ($afi->type == 1 )
                    {
                       if ($comp == 1 && $datos->total_pension >= $procedure->indicator)
                       {
                          $ecomplement->eco_com_modality_id = 4;
                       }
                       elseif ($comp == 1 && $datos->total_pension < $procedure->indicator)
                       {
                          $ecomplement->eco_com_modality_id = 6;
                       }
                       elseif ($comp > 1 && $datos->total_pension < $procedure->indicator)
                       {
                          $ecomplement->eco_com_modality_id = 8;
                       }
                    }
                   //Viudedad
                    elseif ($afi->type == 2) 
                    {
                       if($comp == 1 && $datos->total_pension >= $procedure->indicator) 
                       {
                           $ecomplement->eco_com_modality_id = 5;
                       } elseif ($comp == 1 && $datos->total_pension < $procedure->indicator) 
                       {
                            $ecomplement->eco_com_modality_id = 7;
                       } elseif ($comp > 1 && $datos->total_pension < $procedure->indicator ) 
                       {
                           $ecomplement->eco_com_modality_id = 9;
                       }
                    }
                    else
                    { //ORFANDAD
                      if ($comp == 1 && $datos->total_pension >= $procedure->indicator)
                       {
                          $ecomplement->eco_com_modality_id = 10;
                       }
                       elseif ($comp == 1 && $datos->total_pension < $procedure->indicator)
                       {
                          $ecomplement->eco_com_modality_id = 11;
                       }
                       elseif ($comp > 1 && $datos->total_pension < $procedure->indicator)
                       {
                          $ecomplement->eco_com_modality_id = 12;
                       }
                    }
                    $ecomplement->total_rent = $datos->total_pension;
                    $ecomplement->aps_total_cc = $datos->total_cc;
                    $ecomplement->aps_total_fsa = $datos->total_fsa;
                    $ecomplement->aps_total_fs = $datos->total_fs;
                    $ecomplement->rent_type ='Automatico';
                    $ecomplement->save();                  
                    $found ++;
                    Log::info($ci);
                }
              }
              else
              {
                $nofound ++;
                $i ++;
                $list[] = $datos;
              }
            
          }
          
          Session::flash('message', "Importación Exitosa"." F:".$found." NF:".$nofound);
          return redirect('economic_complement');
    }
  }

  public static function import_from_bank(Request $request) {
    //substr_replace($string ,"",-1);
    if($request->hasFile('archive'))
    {
      global $year, $semester, $results,$i,$afi,$list;
      $reader = $request->file('archive');
      $filename = $reader->getRealPath();
      $year = $request->year;
      $semester = $request->semester;
      Excel::load($filename, function($reader) {
              global $results,$i,$afi,$list;
              ini_set('memory_limit', '-1');
              ini_set('max_execution_time', '-1');
              ini_set('max_input_time', '-1');
              set_time_limit('-1');
              $results = collect($reader->get());
      });

      $afi;
      $found=0;
      $nofound=0;
      //return response()->json($results);
      foreach ($results as $valor){
          $ci = substr_replace($valor->carnet,"",-2);
          $card = rtrim($ci);
        //  return response()->json($card);
          $afi = DB::table('economic_complements')
            ->select(DB::raw('economic_complements.*'))
            ->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
            ->where('affiliates.identity_card', '=',rtrim($card))
            ->whereYear('economic_complements.review_date', '=', $year)
            ->where('economic_complements.semester', '=', $semester)
            ->where('economic_complements.eco_com_state_id', '=', 2)->first();
            if ($afi){
                $ecomplement = EconomicComplement::where('affiliate_id','=', $afi->affiliate_id)->whereYear('review_date','=', $afi->review_date)->where('semester','=', $afi->semester)->where('eco_com_state_id','=', $afi->eco_com_state_id)->first();
                $ecomplement->eco_com_state_id = 4;
                $ecomplement->total = $valor->monto_asignado;
                $ecomplement->payment_number = $valor->nro_comprobante;
                $ecomplement->payment_date = $valor->fecha_pago;
                $ecomplement->save();
                $found ++;
            }
            else{
              $nofound ++;
              $i ++;
              $list[]= $valor;
            }
        }
        //return response()->json($list);
        //export record no found
        Excel::create('REPORTE_BANCO_NO_ENCONTRADO', function($excel) {
            global $list, $j,$k;
            $j = 2;
            $k=1;
            $excel->sheet('Lista_Banco', function($sheet) {
            global $list, $j,$k;
            $sheet->row(1, array('NRO', 'DEPARTAMENTO','CARNET','NOMBRE','MONTO_ASIGNADO','DESCRIPCION1','DESCRIPCION2','NRO_COMPROBANTE','FECHA_PAGO'));
            foreach ($list as $datos) {
                $sheet->row($j, array($k,$datos->departamento,$datos->carnet,$datos->nombre, $datos->monto_asignado,$datos->descripcion1,$datos->descripcion2,$datos->nro_comprobante,$datos->fecha_pago));
                $j++;
                $k++;
            }

          });

        })->export('xlsx');
        Session::flash('message', "Importación Exitosa"." F:".$found." NF:".$nofound);
        return redirect('economic_complement');
  }

  }

    //############################################## EXPORT AFFILIATES TO APS ###################################
    public function export_to_aps(Request $request)
    {
        global $year, $semester,$i,$afi;
       $year = $request->year;
       $semester = $request->semester;
       Excel::create('Muserpol_para_aps', function($excel) {
                 global $year,$semester, $j;
                 $j = 2;
                 $excel->sheet("AFILIADOS_PARA_APS_".$year, function($sheet) {
                 global $year,$semester, $j, $i;
                 $i=1;
                 $sheet->row(1, array('NRO', 'TIPO_ID', 'NUM_ID', 'EXTENSION', 'CUA', 'PRIMER_APELLIDO_T', 'SEGUNDO_APELLIDO_T','PRIMER_NOMBRE_T','SEGUNDO_NOMBRE_T','APELLIDO_CASADA_T','FECHA_NACIMIENTO_T'));
                 $afi = DB::table('eco_com_applicants')
                     ->select(DB::raw('economic_complements.id,economic_complements.affiliate_id,affiliates.identity_card,cities.third_shortened,affiliates.nua,affiliates.last_name,affiliates.mothers_last_name,affiliates.first_name,affiliates.second_name,affiliates.surname_husband,affiliates.birth_date'))
                     ->leftJoin('economic_complements','eco_com_applicants.economic_complement_id','=','economic_complements.id')
                     ->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
                     ->leftJoin('cities', 'affiliates.city_identity_card_id', '=', 'cities.id')
                     ->where('affiliates.pension_entity_id','<>', 5)
                     //->where('economic_complements.sub_total_rent','>', 0)
                     //->whereNull('economic_complements.total_rent')
                     ->whereYear('economic_complements.year', '=', $year)                     
                     ->where('economic_complements.semester', '=', $semester)->get();
                 foreach ($afi as $datos) {
                     $sheet->row($j, array($i, "I",Util::addcero($datos->identity_card,13),$datos->third_shortened,Util::addcero($datos->nua,9), $datos->last_name, $datos->mothers_last_name,$datos->first_name, $datos->second_name, $datos->surname_husband,Util::DateUnion($datos->birth_date)));
                     $j++;
                     $i++;
                 }
               });
           })->export('xlsx');
             Session::flash('message', "Importación Exitosa");
             return redirect('economic_complement');
    }

    public function export_to_bank(Request $request)
    {
      global $year, $semester,$i,$afi,$semester1;
      $year = $request->year;
      $semester = $request->semester;
      $afi = DB::table('eco_com_applicants')
          ->select(DB::raw("economic_complements.id,economic_complements.affiliate_id,economic_complements.semester,cities0.second_shortened as regional,eco_com_applicants.identity_card,cities1.first_shortened as ext,concat_ws(' ', NULLIF(eco_com_applicants.first_name,null), NULLIF(eco_com_applicants.second_name, null), NULLIF(eco_com_applicants.last_name, null), NULLIF(eco_com_applicants.mothers_last_name, null), NULLIF(eco_com_applicants.surname_husband, null)) as full_name,economic_complements.total as importe,eco_com_modalities.shortened as modality,degrees.shortened as degree"))
          ->leftJoin('economic_complements','eco_com_applicants.economic_complement_id','=','economic_complements.id')
          ->leftJoin('cities as cities0', 'economic_complements.city_id', '=', 'cities0.id')
          ->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
          ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')          
          ->leftJoin('cities as cities1', 'eco_com_applicants.city_identity_card_id', '=', 'cities1.id')
          ->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')         
          ->whereYear('economic_complements.year', '=', $year)
          ->where('economic_complements.semester', '=', $semester)
          ->where('economic_complements.workflow_id','=',1)
          ->where('economic_complements.wf_current_state_id',2)
          ->where('economic_complements.state','Edited')
          ->where('economic_complements.total','>', 0)
          ->whereRaw('economic_complements.total_rent::numeric < economic_complements.salary_quotable::numeric')         
          ->whereRaw("not exists(select affiliates.id from affiliate_observations where affiliates.id = affiliate_observations.affiliate_id and affiliate_observations.observation_type_id IN(1,2,3,12))")         
          ->whereNotNull('economic_complements.review_date')->get();     
      

      if($afi){
            if($semester == "Primer")
            {
              $semester1 = "MUSERPOL PAGO COMPLEMENTO ECONOMICO 1ER SEM ".$year;
              $abv ="Pago_Banco_Union_1ER_SEM_".$year;
            }
            else{
              $semester1 = "MUSERPOL PAGO COMPLEMENTO ECONOMICO 2DO SEM ".$year;
              $abv ="Pago_Banco_Union_2DO_SEM_".$year;
            }
            Excel::create($abv, function($excel) {
                global $year,$semester,$afi,$j,$semester1;
                $j = 2;
                $excel->sheet("AFILIADOS_PARA_APS_".$year, function($sheet) {
                  $sheet->setColumnFormat(array(
                      'D' => '0,000.00'
                  ));
                global $year,$semester, $afi,$j, $i,$semester1;
                $i=1;
                $sheet->row(1, array('DEPARTAMENTO','IDENTIFICACION','NOMBRE_Y_APELLIDO','IMPORTE_A_PAGAR','MONEDA_DEL_IMPORTE','DESCRIPCION_1','DESCRIPCION_2','DESCRIPCION_3'));
             
                foreach ($afi as $datos) 
                {
                    $economic =  EconomicComplement::idIs($datos->id)->first();

                    $import = $datos->importe;                       
                    if ($economic->has_legal_guardian)
                    {
                     
                      $legal1 = EconomicComplementLegalGuardian::where('economic_complement_id','=', $economic->id)->first();
                      $sheet->row($j, array($datos->regional,$legal1->identity_card." ".$legal1->city_identity_card->first_shortened,$legal1->getFullName(), $import,"1",$datos->modality." - ".$datos->degree,$datos->affiliate_id,$semester1));                     
                      
                    }
                    else
                    {
                      
                      $apl =EconomicComplement::find($datos->id)->economic_complement_applicant;
                      $sheet->row($j, array($datos->regional,$datos->identity_card." ".$datos->ext,$apl->getFullName(), $import,"1",$datos->modality." - ".$datos->degree,$datos->affiliate_id,$semester1));  

                    }                   
                    
                    $j++;
                   
                }    
                           
              });
          })->export('xlsx');
          return redirect('economic_complement');
          Session::flash('message', "Importación Exitosa");

      }
      else {

        Session::flash('message', "No existen registros para exportar");
        return redirect('economic_complement');

      }


    }

    /* David */
    public function export_excel()
    {
        // $complementos = EconomicComplement::where('workflow_id','=','1')
        //                               // ->where('wf_current_state_id','=','2')
        //                               ->where('state','=','Edited')
        //                               ->get();   
      if(Auth::check())
      {
        $user_role_id=Auth::user()->roles()->first();
        Log::info("user_role_id = ".$user_role_id->id);
        $semestre = DB::table('eco_com_procedures')->orderBy('id','DESC')->first();

         $economic_complements=EconomicComplement::where('eco_com_state_id',16)
            ->leftJoin('eco_com_applicants','economic_complements.id','=','eco_com_applicants.economic_complement_id')
            ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
            ->leftJoin('eco_com_procedures','economic_complements.eco_com_procedure_id','=','eco_com_procedures.id')
            ->leftJoin('cities','economic_complements.city_id','=','cities.id')
            ->leftJoin('categories','economic_complements.category_id','=','categories.id')
            ->leftJoin('base_wages','economic_complements.base_wage_id','=','base_wages.id')
            ->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')

            ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
            ->leftJoin('degrees','affiliates.degree_id','=','degrees.id')
            ->leftJoin('affiliate_observations','affiliates.id','=','affiliate_observations.affiliate_id')

            ->where('economic_complements.workflow_id','=','1')
            ->where('economic_complements.wf_current_state_id','2')
            ->where('economic_complements.state','Edited')
          //  ->where('economic_complements.eco_com_procedure_id',$semestre->id)
            ->where('economic_complements.eco_com_procedure_id','2')
            //->where('economic_complements.user_id',Auth::user()->id)


            ->select('economic_complements.review_date as Fecha','eco_com_applicants.identity_card as CI','cities.first_shortened as Exp_complemento','eco_com_applicants.first_name as Primer_nombre','eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno','eco_com_applicants.mothers_last_name as Materno','eco_com_applicants.surname_husband as ap_esp','eco_com_applicants.birth_date as Fecha_nac','eco_com_applicants.nua','eco_com_applicants.phone_number as Telefono','eco_com_applicants.cell_phone_number as celular','eco_com_modalities.shortened as tipo_renta','eco_com_procedures.year as año_gestion','eco_com_procedures.semester as semestre','categories.name as categoria','degrees.shortened as Grado','base_wages.amount as Sueldo_base','economic_complements.code as Nro_proceso','pension_entities.name as Ente_gestor','affiliate_observations.date as Fecha_obs','affiliate_observations.message as Observacion')
           // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
            ->orderBy('economic_complements.review_date','ASC')
            ->get();

       //  return $economic_complements;
        //$fila = new CustomCollection(array('identificador' => ,$economic_complements-> ));
         Excel::create('Reporte General '.date("Y-m-d H:i:s"),function($excel) use ($economic_complements)
         {
                    
            
                        $excel->sheet('Reporte General',function($sheet) use ($economic_complements) {

                        $sheet->fromArray($economic_complements);
                        // $sheet->fromArray(
                        //                     array(
                        //                            $rows
                        //                           )
                        //                   );

                          // $sheet->row(1,array('Contribuciones: '.$contribuciones->count(),'Total Bs: '.$total) );

                          // $sheet->cells('A1:B1', function($cells) {
                          // $cells->setBackground('#4CCCD4');
                                                      // manipulate the range of cells

                          });
                  
                })->download('xls');

        //return $economic_complements;
       // return "contribuciones totales ".$economic_complements->count();
      }
      else
      {
        return "funcion no disponible revise su sesion de usuario";
      }
    }
     public function export_excel_user()
    {


        // $complementos = EconomicComplement::where('workflow_id','=','1')
        //                               // ->where('wf_current_state_id','=','2')
        //                               ->where('state','=','Edited')
        //                               ->get();   
      if(Auth::check())
      {
        $user_role_id=Auth::user()->roles()->first();
        //Log::info("user_role_id = ".$user_role_id->id);
        $semestre = DB::table('eco_com_procedures')->orderBy('id','DESC')->first();
      //  Log::info($semestre->id);
         $economic_complements=EconomicComplement::where('eco_com_state_id',16)
            ->leftJoin('eco_com_applicants','economic_complements.id','=','eco_com_applicants.economic_complement_id')
            ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
            ->leftJoin('eco_com_procedures','economic_complements.eco_com_procedure_id','=','eco_com_procedures.id')
            ->leftJoin('cities','economic_complements.city_id','=','cities.id')
            ->leftJoin('categories','economic_complements.category_id','=','categories.id')
            ->leftJoin('base_wages','economic_complements.base_wage_id','=','base_wages.id')
            ->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')

            ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
            ->leftJoin('degrees','affiliates.degree_id','=','degrees.id')
            ->leftJoin('affiliate_observations','affiliates.id','=','affiliate_observations.affiliate_id')

            ->where('economic_complements.workflow_id','=','1')
            ->where('economic_complements.wf_current_state_id','2')
            ->where('economic_complements.state','Edited')
            ->where('economic_complements.eco_com_procedure_id','2')
            ->where('economic_complements.user_id',Auth::user()->id)


            ->select('economic_complements.review_date as Fecha','eco_com_applicants.identity_card as CI','cities.first_shortened as Exp','eco_com_applicants.first_name as Primer_nombre','eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno','eco_com_applicants.mothers_last_name as Materno','eco_com_applicants.surname_husband as ap_esp','eco_com_applicants.birth_date as Fecha_nac','eco_com_applicants.nua','eco_com_applicants.phone_number as Telefono','eco_com_applicants.cell_phone_number as celular','eco_com_modalities.shortened as tipo_renta','eco_com_procedures.year as año_gestion','eco_com_procedures.semester as semestre','categories.name as categoria','degrees.shortened as Grado','base_wages.amount as Sueldo_base','economic_complements.code as Nro_proceso','pension_entities.name as Ente_gestor','affiliate_observations.date as Fecha_obs','affiliate_observations.message as Observacion')
             ->orderBy('economic_complements.review_date','ASC')
           // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
            ->get();

       //  return $economic_complements;
        //$fila = new CustomCollection(array('identificador' => ,$economic_complements-> ));
         Excel::create('Reporte '.date("Y-m-d H:i:s").' - '.Auth::user()->first_name.' '.Auth::user()->last_name,function($excel) use ($economic_complements)
         {
                    
            
                        $excel->sheet('Reporte usuario',function($sheet) use ($economic_complements) {

                        $sheet->fromArray($economic_complements);
                        // $sheet->fromArray(
                        //                     array(
                        //                            $rows
                        //                           )
                        //                   );

                          // $sheet->row(1,array('Contribuciones: '.$contribuciones->count(),'Total Bs: '.$total) );

                          // $sheet->cells('A1:B1', function($cells) {
                          // $cells->setBackground('#4CCCD4');
                                                      // manipulate the range of cells

                          });
                  
                })->download('xls');

        //return $economic_complements;
       // return "contribuciones totales ".$economic_complements->count();
      }
      else
      {
        return "funcion no disponible revise su sesion de usuario";
      }
    }
    public function export_excel_general()
    {
      // $complementos = EconomicComplement::where('workflow_id','=','1')
        //                               // ->where('wf_current_state_id','=','2')
        //                               ->where('state','=','Edited')
        //                               ->get();   
      if(Auth::check())
      {
        $user_role_id=Auth::user()->roles()->first();
        Log::info("user_role_id = ".$user_role_id->id);
        $semestre = DB::table('eco_com_procedures')->orderBy('id','DESC')->first();

         $economic_complements=EconomicComplement::whereNotNull('total_rent')
            ->leftJoin('eco_com_applicants','economic_complements.id','=','eco_com_applicants.economic_complement_id')
            ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
            ->leftJoin('eco_com_procedures','economic_complements.eco_com_procedure_id','=','eco_com_procedures.id')
            ->leftJoin('cities','economic_complements.city_id','=','cities.id')
            ->leftJoin('categories','economic_complements.category_id','=','categories.id')
            ->leftJoin('base_wages','economic_complements.base_wage_id','=','base_wages.id')
            ->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')

            ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
            ->leftJoin('degrees','affiliates.degree_id','=','degrees.id')
            ->leftJoin('affiliate_observations','affiliates.id','=','affiliate_observations.affiliate_id')

            // ->where('economic_complements.workflow_id','=','1')
            // ->where('economic_complements.wf_current_state_id','2')
            // ->where('economic_complements.state','Edited')
          //  ->where('economic_complements.eco_com_procedure_id',$semestre->id)
            ->where('economic_complements.eco_com_procedure_id','2')
            //->where('economic_complements.user_id',Auth::user()->id)

            ->distinct('economic_complements.id')
            ->select('economic_complements.id','eco_com_applicants.identity_card as CI','cities.first_shortened as Exp','eco_com_applicants.first_name as Primer_nombre','eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno','eco_com_applicants.mothers_last_name as Materno','eco_com_applicants.surname_husband as ap_esp','eco_com_applicants.birth_date as Fecha_nac','eco_com_applicants.nua','eco_com_applicants.phone_number as Telefono','eco_com_applicants.cell_phone_number as celular','eco_com_modalities.shortened as tipo_renta','eco_com_procedures.year as año_gestion','eco_com_procedures.semester as semestre','categories.name as categoria','degrees.shortened as Grado','economic_complements.total_rent as Renta_total','base_wages.amount as Sueldo_base','economic_complements.seniority as antiguedad','economic_complements.salary_quotable as Salario_cotizable','economic_complements.difference as direfencia','economic_complements.total_amount_semester as monto_total_semestre','economic_complements.complementary_factor as factor_de_complementacion','economic_complements.total','economic_complements.code as Nro_proceso','pension_entities.name as Ente_gestor','affiliate_observations.date as Fecha_obs','affiliate_observations.message as Observacion')
           // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
            // ->orderBy('economic_complements.review_date','ASC')
            ->get();

       //  return $economic_complements;
        //$fila = new CustomCollection(array('identificador' => ,$economic_complements-> ));
         Excel::create('Reporte General '.date("Y-m-d H:i:s"),function($excel) use ($economic_complements)
         {
                    
            
                        $excel->sheet('Reporte General Complemento',function($sheet) use ($economic_complements) {

                        $sheet->fromArray($economic_complements);
                        // $sheet->fromArray(
                        //                     array(
                        //                            $rows
                        //                           )
                        //                   );

                          // $sheet->row(1,array('Contribuciones: '.$contribuciones->count(),'Total Bs: '.$total) );

                          // $sheet->cells('A1:B1', function($cells) {
                          // $cells->setBackground('#4CCCD4');
                                                      // manipulate the range of cells

                          });
                  
                })->download('xls');

        //return $economic_complements;
       // return "contribuciones totales ".$economic_complements->count();
      }
      else
      {
        return "funcion no disponible revise su sesion de usuario";
      }
    }

    public function export_excel_observations()
    {
      if(Auth::check())
      {

 
        global $com_obser_contabilidad_1,$com_obser_prestamos_2,$com_obser_juridica_3,$com_obser_fueraplz90_4,$com_obser_fueraplz120_5,$com_obser_faltareq_6,$com_obser_habitualinclusion7,$com_obser_menor16anos_8,$com_obser_invalidez_9,$com_obser_salario_10,$com_obser_pagodomicilio_12;




         
        $afiliados = DB::table('v_observados')->get();
        
        $a = array();
        foreach ($afiliados as $afiliado) {

          # code...
          $complementos = DB::table("economic_complements")->where('affiliate_id',$afiliado->id)->where('eco_com_procedure_id','=','2')->first();
          if($complementos){
             array_push($a, $afiliado->id);
          }
         
        }
        $afiliados = DB::table('v_observados')->whereIn('id',$a)->get();

        $com_obser_contabilidad_1 = array();
        $com_obser_prestamos_2 = array();
        $com_obser_juridica_3 = array();
        $com_obser_fueraplz90_4 = array();
        $com_obser_fueraplz120_5 = array();
        $com_obser_faltareq_6 = array();
        $com_obser_habitualinclusion7 = array();
        $com_obser_menor16anos_8 = array();
        $com_obser_invalidez_9 = array();
        $com_obser_salario_10 = array();
        $com_obser_pagodomicilio_12 = array();


        foreach ($afiliados as $afiliado) {
          # code...
             $complementos = DB::table("economic_complements")->where('affiliate_id',$afiliado->id)->where('eco_com_procedure_id','=','2')->get();
             if($complementos)
             {
               switch ($afiliado->observation_type_id) {
                 
                 case 1:
                   # code...
                        foreach ($complementos as $complemento) {
                          # code...
                          array_push($com_obser_contabilidad_1, $complemento->id);
                        }
                   break;
                 
                 case 2:
                   # code...
                        foreach ($complementos as $complemento) {

                          # code...
                          array_push($com_obser_prestamos_2, $complemento->id);
                        }
                   break;
                 
                 case 3:
                   # code...
                        foreach ($complementos as $key => $value) {
                          # code...
                          array_push($com_obser_juridica_3, $complemento->id);
                        }
                   break;
                 
                 case 4:
                   # code...
                        foreach ($complementos as $key => $value) {
                          # code...
                          array_push($com_obser_fueraplz90_4, $complemento->id);
                        }
                   break;
                 
                 case 5:
                   # code...
                        foreach ($complementos as $key => $value) {
                          # code...
                          array_push($com_obser_fueraplz120_5, $complemento->id);
                        }
                   break;
                 
                 case 6:
                   # code...
                        foreach ($complementos as $key => $value) {
                          # code...
                          array_push($com_obser_faltareq_6, $complemento->id);
                        }
                   break;
                 
                 case 7:
                   # code...
                        foreach ($complementos as $key => $value) {
                          # code...
                          array_push($com_obser_habitualinclusion7, $complemento->id);
                        }
                   break;
                 
                 case 8:
                   # code...
                        foreach ($complementos as $key => $value) {
                          # code...
                          array_push($com_obser_menor16anos_8, $complemento->id);
                        }
                   break;
                 
                 case 9:
                   # code..
                        foreach ($complementos as $key => $value) {
                          # code...
                          array_push($com_obser_invalidez_9, $complemento->id);
                        }
                   break;
                 
                 case 10:
                   # code...
                        foreach ($complementos as $key => $value) {
                          # code...
                          array_push($com_obser_salario_10, $complemento->id);
                        }
                   break;
                 
                 case 12:
                   # code...
                        foreach ($complementos as $key => $value) {
                          # code...
                          array_push($com_obser_pagodomicilio_12, $complemento->id);
                        }
                   break;
                 


                 default:
                   # code...
                   break;
               }
               
             }

        }


        
       //  return $economic_complements;
        //$fila = new CustomCollection(array('identificador' => ,$economic_complements-> ));
         Excel::create('Reporte General '.date("Y-m-d H:i:s"),function($excel) 
         {
                     global $com_obser_contabilidad_1,$com_obser_prestamos_2,$com_obser_juridica_3,$com_obser_fueraplz90_4,$com_obser_fueraplz120_5,$com_obser_faltareq_6,$com_obser_habitualinclusion7,$com_obser_menor16anos_8,$com_obser_invalidez_9,$com_obser_salario_10,$com_obser_pagodomicilio_12;
            
                        $excel->sheet('Observacion por contabilidad ',function($sheet) {

                         global $com_obser_contabilidad_1,$com_obser_prestamos_2,$com_obser_juridica_3,$com_obser_fueraplz90_4,$com_obser_fueraplz120_5,$com_obser_faltareq_6,$com_obser_habitualinclusion7,$com_obser_menor16anos_8,$com_obser_invalidez_9,$com_obser_salario_10,$com_obser_pagodomicilio_12;  

                        
                         $economic_complements=EconomicComplement::whereIn('economic_complements.id',$com_obser_contabilidad_1)
                          ->leftJoin('eco_com_applicants','economic_complements.id','=','eco_com_applicants.economic_complement_id')
                          ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                          ->leftJoin('eco_com_procedures','economic_complements.eco_com_procedure_id','=','eco_com_procedures.id')
                          ->leftJoin('cities as city_com','economic_complements.city_id','=','city_com.id')
                          ->leftJoin('cities as city_ben','eco_com_applicants.city_identity_card_id','=','city_ben.id')
                          ->leftJoin('categories','economic_complements.category_id','=','categories.id')
                          ->leftJoin('base_wages','economic_complements.base_wage_id','=','base_wages.id')
                          ->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')

                          ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
                          ->leftJoin('degrees','affiliates.degree_id','=','degrees.id')
                          ->leftJoin('affiliate_observations','affiliates.id','=','affiliate_observations.affiliate_id')

                          // ->where('economic_complements.workflow_id','=','1')
                          // ->where('economic_complements.wf_current_state_id','2')
                          // ->where('economic_complements.state','Edited')
                          //  ->where('economic_complements.eco_com_procedure_id',$semestre->id)
                          // ->where('economic_complements.eco_com_procedure_id','2')

                          //->where('economic_complements.user_id',Auth::user()->id)

                          ->distinct('economic_complements.id')
                          ->select('economic_complements.id as Id','economic_complements.code as Nro_tramite','eco_com_applicants.first_name as Primer_nombre','eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno','eco_com_applicants.mothers_last_name as Materno','eco_com_applicants.identity_card as CI','cities.first_shortened as Ext','eco_com_applicants.birth_date as Fecha_nac','eco_com_applicants.nua','eco_com_applicants.phone_number as Telefono','eco_com_applicants.cell_phone_number as celular','eco_com_modalities.shortened as tipo_renta','eco_com_procedures.year as año_gestion','eco_com_procedures.semester as semestre','categories.name as categoria','degrees.shortened as Grado','economic_complements.total_rent as Renta_total','base_wages.amount as Sueldo_base','economic_complements.seniority as antiguedad','economic_complements.salary_quotable as Salario_cotizable','economic_complements.difference as direfencia','economic_complements.total_amount_semester as monto_total_semestre','economic_complements.complementary_factor as factor_de_complementacion','economic_complements.total','economic_complements.code as Nro_proceso','pension_entities.name as Ente_gestor','affiliate_observations.date as Fecha_obs','affiliate_observations.message as Observacion')
                         // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
                          // ->orderBy('economic_complements.review_date','ASC')
                          ->get();


                        $sheet->fromArray($economic_complements);


                        });

                        $excel->sheet('Observacion por prestamos ',function($sheet) {

                         global $com_obser_contabilidad_1,$com_obser_prestamos_2,$com_obser_juridica_3,$com_obser_fueraplz90_4,$com_obser_fueraplz120_5,$com_obser_faltareq_6,$com_obser_habitualinclusion7,$com_obser_menor16anos_8,$com_obser_invalidez_9,$com_obser_salario_10,$com_obser_pagodomicilio_12;  

                        
                         $economic_complements=EconomicComplement::whereIn('economic_complements.id',$com_obser_prestamos_2)
                          ->leftJoin('eco_com_applicants','economic_complements.id','=','eco_com_applicants.economic_complement_id')
                          ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                          ->leftJoin('eco_com_procedures','economic_complements.eco_com_procedure_id','=','eco_com_procedures.id')
                          ->leftJoin('cities','economic_complements.city_id','=','cities.id')
                          ->leftJoin('categories','economic_complements.category_id','=','categories.id')
                          ->leftJoin('base_wages','economic_complements.base_wage_id','=','base_wages.id')
                          ->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')

                          ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
                          ->leftJoin('degrees','affiliates.degree_id','=','degrees.id')
                          ->leftJoin('affiliate_observations','affiliates.id','=','affiliate_observations.affiliate_id')

                          // ->where('economic_complements.workflow_id','=','1')
                          // ->where('economic_complements.wf_current_state_id','2')
                          // ->where('economic_complements.state','Edited')
                          //  ->where('economic_complements.eco_com_procedure_id',$semestre->id)
                          // ->where('economic_complements.eco_com_procedure_id','2')

                          //->where('economic_complements.user_id',Auth::user()->id)

                          ->distinct('economic_complements.id')
                          ->select('economic_complements.id','eco_com_applicants.identity_card as CI','cities.first_shortened as Exp','eco_com_applicants.first_name as Primer_nombre','eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno','eco_com_applicants.mothers_last_name as Materno','eco_com_applicants.surname_husband as ap_esp','eco_com_applicants.birth_date as Fecha_nac','eco_com_applicants.nua','eco_com_applicants.phone_number as Telefono','eco_com_applicants.cell_phone_number as celular','eco_com_modalities.shortened as tipo_renta','eco_com_procedures.year as año_gestion','eco_com_procedures.semester as semestre','categories.name as categoria','degrees.shortened as Grado','economic_complements.total_rent as Renta_total','base_wages.amount as Sueldo_base','economic_complements.seniority as antiguedad','economic_complements.salary_quotable as Salario_cotizable','economic_complements.difference as direfencia','economic_complements.total_amount_semester as monto_total_semestre','economic_complements.complementary_factor as factor_de_complementacion','economic_complements.total','economic_complements.code as Nro_proceso','pension_entities.name as Ente_gestor','affiliate_observations.date as Fecha_obs','affiliate_observations.message as Observacion')
                         // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
                          // ->orderBy('economic_complements.review_date','ASC')
                          ->get();



                        $sheet->fromArray($economic_complements);


                        });

                        $excel->sheet('Observacion por juridica ',function($sheet) {

                         global $com_obser_contabilidad_1,$com_obser_prestamos_2,$com_obser_juridica_3,$com_obser_fueraplz90_4,$com_obser_fueraplz120_5,$com_obser_faltareq_6,$com_obser_habitualinclusion7,$com_obser_menor16anos_8,$com_obser_invalidez_9,$com_obser_salario_10,$com_obser_pagodomicilio_12;  

                        
                         $economic_complements=EconomicComplement::whereIn('economic_complements.id',$com_obser_juridica_3)
                          ->leftJoin('eco_com_applicants','economic_complements.id','=','eco_com_applicants.economic_complement_id')
                          ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                          ->leftJoin('eco_com_procedures','economic_complements.eco_com_procedure_id','=','eco_com_procedures.id')
                          ->leftJoin('cities as city_com','economic_complements.city_id','=','city_com.id')
                          ->leftJoin('cities as city_ben','eco_com_applicants.city_identity_card_id','=','city_ben.id')
                          ->leftJoin('categories','economic_complements.category_id','=','categories.id')
                          ->leftJoin('base_wages','economic_complements.base_wage_id','=','base_wages.id')
                          ->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')

                          ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
                          ->leftJoin('degrees','affiliates.degree_id','=','degrees.id')
                          ->leftJoin('affiliate_observations','affiliates.id','=','affiliate_observations.affiliate_id')

                          // ->where('economic_complements.workflow_id','=','1')
                          // ->where('economic_complements.wf_current_state_id','2')
                          // ->where('economic_complements.state','Edited')
                          //  ->where('economic_complements.eco_com_procedure_id',$semestre->id)
                          // ->where('economic_complements.eco_com_procedure_id','2')

                          //->where('economic_complements.user_id',Auth::user()->id)

                          ->distinct('economic_complements.id')
                          ->select('economic_complements.id','eco_com_applicants.identity_card as CI','city_ben.first_shortened as Ext','city_com.name as Regional','eco_com_applicants.first_name as Primer_nombre','eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno','eco_com_applicants.mothers_last_name as Materno','eco_com_applicants.surname_husband as ap_esp','eco_com_applicants.birth_date as Fecha_nac','eco_com_applicants.nua','eco_com_applicants.phone_number as Telefono','eco_com_applicants.cell_phone_number as celular','eco_com_modalities.shortened as tipo_renta','eco_com_procedures.year as año_gestion','eco_com_procedures.semester as semestre','categories.name as categoria','degrees.shortened as Grado','economic_complements.total_rent as Renta_total','base_wages.amount as Sueldo_base','economic_complements.seniority as antiguedad','economic_complements.salary_quotable as Salario_cotizable','economic_complements.difference as direfencia','economic_complements.total_amount_semester as monto_total_semestre','economic_complements.complementary_factor as factor_de_complementacion','economic_complements.total','economic_complements.code as Nro_proceso','pension_entities.name as Ente_gestor','affiliate_observations.date as Fecha_obs','affiliate_observations.message as Observacion')
                         // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
                          // ->orderBy('economic_complements.review_date','ASC')
                          ->get();



                        $sheet->fromArray($economic_complements);


                        });


                        $excel->sheet('Fuera de Plazo 90 días',function($sheet) {

                         global $com_obser_contabilidad_1,$com_obser_prestamos_2,$com_obser_juridica_3,$com_obser_fueraplz90_4,$com_obser_fueraplz120_5,$com_obser_faltareq_6,$com_obser_habitualinclusion7,$com_obser_menor16anos_8,$com_obser_invalidez_9,$com_obser_salario_10,$com_obser_pagodomicilio_12;  

                        
                         $economic_complements=EconomicComplement::whereIn('economic_complements.id',$com_obser_fueraplz90_4)
                          ->leftJoin('eco_com_applicants','economic_complements.id','=','eco_com_applicants.economic_complement_id')
                          ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                          ->leftJoin('eco_com_procedures','economic_complements.eco_com_procedure_id','=','eco_com_procedures.id')
                          ->leftJoin('cities','economic_complements.city_id','=','cities.id')
                          ->leftJoin('categories','economic_complements.category_id','=','categories.id')
                          ->leftJoin('base_wages','economic_complements.base_wage_id','=','base_wages.id')
                          ->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')

                          ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
                          ->leftJoin('degrees','affiliates.degree_id','=','degrees.id')
                          ->leftJoin('affiliate_observations','affiliates.id','=','affiliate_observations.affiliate_id')

                          // ->where('economic_complements.workflow_id','=','1')
                          // ->where('economic_complements.wf_current_state_id','2')
                          // ->where('economic_complements.state','Edited')
                          //  ->where('economic_complements.eco_com_procedure_id',$semestre->id)
                          // ->where('economic_complements.eco_com_procedure_id','2')

                          //->where('economic_complements.user_id',Auth::user()->id)

                          ->distinct('economic_complements.id')
                          ->select('economic_complements.id','eco_com_applicants.identity_card as CI','cities.first_shortened as Exp','eco_com_applicants.first_name as Primer_nombre','eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno','eco_com_applicants.mothers_last_name as Materno','eco_com_applicants.surname_husband as ap_esp','eco_com_applicants.birth_date as Fecha_nac','eco_com_applicants.nua','eco_com_applicants.phone_number as Telefono','eco_com_applicants.cell_phone_number as celular','eco_com_modalities.shortened as tipo_renta','eco_com_procedures.year as año_gestion','eco_com_procedures.semester as semestre','categories.name as categoria','degrees.shortened as Grado','economic_complements.total_rent as Renta_total','base_wages.amount as Sueldo_base','economic_complements.seniority as antiguedad','economic_complements.salary_quotable as Salario_cotizable','economic_complements.difference as direfencia','economic_complements.total_amount_semester as monto_total_semestre','economic_complements.complementary_factor as factor_de_complementacion','economic_complements.total','economic_complements.code as Nro_proceso','pension_entities.name as Ente_gestor','affiliate_observations.date as Fecha_obs','affiliate_observations.message as Observacion')
                         // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
                          // ->orderBy('economic_complements.review_date','ASC')
                          ->get();



                        $sheet->fromArray($economic_complements);


                        });

                        $excel->sheet('Fuera de Plazo 120 días',function($sheet) {

                         global $com_obser_contabilidad_1,$com_obser_prestamos_2,$com_obser_juridica_3,$com_obser_fueraplz90_4,$com_obser_fueraplz120_5,$com_obser_faltareq_6,$com_obser_habitualinclusion7,$com_obser_menor16anos_8,$com_obser_invalidez_9,$com_obser_salario_10,$com_obser_pagodomicilio_12;  

                        
                         $economic_complements=EconomicComplement::whereIn('economic_complements.id',$com_obser_fueraplz120_5)
                          ->leftJoin('eco_com_applicants','economic_complements.id','=','eco_com_applicants.economic_complement_id')
                          ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                          ->leftJoin('eco_com_procedures','economic_complements.eco_com_procedure_id','=','eco_com_procedures.id')
                          ->leftJoin('cities','economic_complements.city_id','=','cities.id')
                          ->leftJoin('categories','economic_complements.category_id','=','categories.id')
                          ->leftJoin('base_wages','economic_complements.base_wage_id','=','base_wages.id')
                          ->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')

                          ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
                          ->leftJoin('degrees','affiliates.degree_id','=','degrees.id')
                          ->leftJoin('affiliate_observations','affiliates.id','=','affiliate_observations.affiliate_id')

                          // ->where('economic_complements.workflow_id','=','1')
                          // ->where('economic_complements.wf_current_state_id','2')
                          // ->where('economic_complements.state','Edited')
                          //  ->where('economic_complements.eco_com_procedure_id',$semestre->id)
                          // ->where('economic_complements.eco_com_procedure_id','2')

                          //->where('economic_complements.user_id',Auth::user()->id)

                          ->distinct('economic_complements.id')
                          ->select('economic_complements.id','eco_com_applicants.identity_card as CI','cities.first_shortened as Exp','eco_com_applicants.first_name as Primer_nombre','eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno','eco_com_applicants.mothers_last_name as Materno','eco_com_applicants.surname_husband as ap_esp','eco_com_applicants.birth_date as Fecha_nac','eco_com_applicants.nua','eco_com_applicants.phone_number as Telefono','eco_com_applicants.cell_phone_number as celular','eco_com_modalities.shortened as tipo_renta','eco_com_procedures.year as año_gestion','eco_com_procedures.semester as semestre','categories.name as categoria','degrees.shortened as Grado','economic_complements.total_rent as Renta_total','base_wages.amount as Sueldo_base','economic_complements.seniority as antiguedad','economic_complements.salary_quotable as Salario_cotizable','economic_complements.difference as direfencia','economic_complements.total_amount_semester as monto_total_semestre','economic_complements.complementary_factor as factor_de_complementacion','economic_complements.total','economic_complements.code as Nro_proceso','pension_entities.name as Ente_gestor','affiliate_observations.date as Fecha_obs','affiliate_observations.message as Observacion')
                         // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
                          // ->orderBy('economic_complements.review_date','ASC')
                          ->get();



                        $sheet->fromArray($economic_complements);


                        });

                        $excel->sheet('Falta de Requisitos',function($sheet) {

                         global $com_obser_contabilidad_1,$com_obser_prestamos_2,$com_obser_juridica_3,$com_obser_fueraplz90_4,$com_obser_fueraplz120_5,$com_obser_faltareq_6,$com_obser_habitualinclusion7,$com_obser_menor16anos_8,$com_obser_invalidez_9,$com_obser_salario_10,$com_obser_pagodomicilio_12;  

                        
                         $economic_complements=EconomicComplement::whereIn('economic_complements.id',$com_obser_faltareq_6)
                          ->leftJoin('eco_com_applicants','economic_complements.id','=','eco_com_applicants.economic_complement_id')
                          ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                          ->leftJoin('eco_com_procedures','economic_complements.eco_com_procedure_id','=','eco_com_procedures.id')
                          ->leftJoin('cities','economic_complements.city_id','=','cities.id')
                          ->leftJoin('categories','economic_complements.category_id','=','categories.id')
                          ->leftJoin('base_wages','economic_complements.base_wage_id','=','base_wages.id')
                          ->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')

                          ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
                          ->leftJoin('degrees','affiliates.degree_id','=','degrees.id')
                          ->leftJoin('affiliate_observations','affiliates.id','=','affiliate_observations.affiliate_id')

                          // ->where('economic_complements.workflow_id','=','1')
                          // ->where('economic_complements.wf_current_state_id','2')
                          // ->where('economic_complements.state','Edited')
                          //  ->where('economic_complements.eco_com_procedure_id',$semestre->id)
                          // ->where('economic_complements.eco_com_procedure_id','2')

                          //->where('economic_complements.user_id',Auth::user()->id)

                          ->distinct('economic_complements.id')
                          ->select('economic_complements.id','eco_com_applicants.identity_card as CI','cities.first_shortened as Exp','eco_com_applicants.first_name as Primer_nombre','eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno','eco_com_applicants.mothers_last_name as Materno','eco_com_applicants.surname_husband as ap_esp','eco_com_applicants.birth_date as Fecha_nac','eco_com_applicants.nua','eco_com_applicants.phone_number as Telefono','eco_com_applicants.cell_phone_number as celular','eco_com_modalities.shortened as tipo_renta','eco_com_procedures.year as año_gestion','eco_com_procedures.semester as semestre','categories.name as categoria','degrees.shortened as Grado','economic_complements.total_rent as Renta_total','base_wages.amount as Sueldo_base','economic_complements.seniority as antiguedad','economic_complements.salary_quotable as Salario_cotizable','economic_complements.difference as direfencia','economic_complements.total_amount_semester as monto_total_semestre','economic_complements.complementary_factor as factor_de_complementacion','economic_complements.total','economic_complements.code as Nro_proceso','pension_entities.name as Ente_gestor','affiliate_observations.date as Fecha_obs','affiliate_observations.message as Observacion')
                         // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
                          // ->orderBy('economic_complements.review_date','ASC')
                          ->get();



                        $sheet->fromArray($economic_complements);


                        });


                        $excel->sheet('Requisitos Hab a Incl',function($sheet) {

                         global $com_obser_contabilidad_1,$com_obser_prestamos_2,$com_obser_juridica_3,$com_obser_fueraplz90_4,$com_obser_fueraplz120_5,$com_obser_faltareq_6,$com_obser_habitualinclusion7,$com_obser_menor16anos_8,$com_obser_invalidez_9,$com_obser_salario_10,$com_obser_pagodomicilio_12;  

                        
                         $economic_complements=EconomicComplement::whereIn('economic_complements.id',$com_obser_habitualinclusion7)
                          ->leftJoin('eco_com_applicants','economic_complements.id','=','eco_com_applicants.economic_complement_id')
                          ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                          ->leftJoin('eco_com_procedures','economic_complements.eco_com_procedure_id','=','eco_com_procedures.id')
                          ->leftJoin('cities','economic_complements.city_id','=','cities.id')
                          ->leftJoin('categories','economic_complements.category_id','=','categories.id')
                          ->leftJoin('base_wages','economic_complements.base_wage_id','=','base_wages.id')
                          ->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')

                          ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
                          ->leftJoin('degrees','affiliates.degree_id','=','degrees.id')
                          ->leftJoin('affiliate_observations','affiliates.id','=','affiliate_observations.affiliate_id')

                          // ->where('economic_complements.workflow_id','=','1')
                          // ->where('economic_complements.wf_current_state_id','2')
                          // ->where('economic_complements.state','Edited')
                          //  ->where('economic_complements.eco_com_procedure_id',$semestre->id)
                          // ->where('economic_complements.eco_com_procedure_id','2')

                          //->where('economic_complements.user_id',Auth::user()->id)

                          ->distinct('economic_complements.id')
                          ->select('economic_complements.id','eco_com_applicants.identity_card as CI','cities.first_shortened as Exp','eco_com_applicants.first_name as Primer_nombre','eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno','eco_com_applicants.mothers_last_name as Materno','eco_com_applicants.surname_husband as ap_esp','eco_com_applicants.birth_date as Fecha_nac','eco_com_applicants.nua','eco_com_applicants.phone_number as Telefono','eco_com_applicants.cell_phone_number as celular','eco_com_modalities.shortened as tipo_renta','eco_com_procedures.year as año_gestion','eco_com_procedures.semester as semestre','categories.name as categoria','degrees.shortened as Grado','economic_complements.total_rent as Renta_total','base_wages.amount as Sueldo_base','economic_complements.seniority as antiguedad','economic_complements.salary_quotable as Salario_cotizable','economic_complements.difference as direfencia','economic_complements.total_amount_semester as monto_total_semestre','economic_complements.complementary_factor as factor_de_complementacion','economic_complements.total','economic_complements.code as Nro_proceso','pension_entities.name as Ente_gestor','affiliate_observations.date as Fecha_obs','affiliate_observations.message as Observacion')
                         // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
                          // ->orderBy('economic_complements.review_date','ASC')
                          ->get();



                        $sheet->fromArray($economic_complements);


                        });

                        $excel->sheet('Menor a 16 años',function($sheet) {

                         global $com_obser_contabilidad_1,$com_obser_prestamos_2,$com_obser_juridica_3,$com_obser_fueraplz90_4,$com_obser_fueraplz120_5,$com_obser_faltareq_6,$com_obser_habitualinclusion7,$com_obser_menor16anos_8,$com_obser_invalidez_9,$com_obser_salario_10,$com_obser_pagodomicilio_12;  

                        
                         $economic_complements=EconomicComplement::whereIn('economic_complements.id',$com_obser_menor16anos_8)
                          ->leftJoin('eco_com_applicants','economic_complements.id','=','eco_com_applicants.economic_complement_id')
                          ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                          ->leftJoin('eco_com_procedures','economic_complements.eco_com_procedure_id','=','eco_com_procedures.id')
                          ->leftJoin('cities','economic_complements.city_id','=','cities.id')
                          ->leftJoin('categories','economic_complements.category_id','=','categories.id')
                          ->leftJoin('base_wages','economic_complements.base_wage_id','=','base_wages.id')
                          ->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')

                          ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
                          ->leftJoin('degrees','affiliates.degree_id','=','degrees.id')
                          ->leftJoin('affiliate_observations','affiliates.id','=','affiliate_observations.affiliate_id')

                          // ->where('economic_complements.workflow_id','=','1')
                          // ->where('economic_complements.wf_current_state_id','2')
                          // ->where('economic_complements.state','Edited')
                          //  ->where('economic_complements.eco_com_procedure_id',$semestre->id)
                          // ->where('economic_complements.eco_com_procedure_id','2')

                          //->where('economic_complements.user_id',Auth::user()->id)

                          ->distinct('economic_complements.id')
                          ->select('economic_complements.id','eco_com_applicants.identity_card as CI','cities.first_shortened as Exp','eco_com_applicants.first_name as Primer_nombre','eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno','eco_com_applicants.mothers_last_name as Materno','eco_com_applicants.surname_husband as ap_esp','eco_com_applicants.birth_date as Fecha_nac','eco_com_applicants.nua','eco_com_applicants.phone_number as Telefono','eco_com_applicants.cell_phone_number as celular','eco_com_modalities.shortened as tipo_renta','eco_com_procedures.year as año_gestion','eco_com_procedures.semester as semestre','categories.name as categoria','degrees.shortened as Grado','economic_complements.total_rent as Renta_total','base_wages.amount as Sueldo_base','economic_complements.seniority as antiguedad','economic_complements.salary_quotable as Salario_cotizable','economic_complements.difference as direfencia','economic_complements.total_amount_semester as monto_total_semestre','economic_complements.complementary_factor as factor_de_complementacion','economic_complements.total','economic_complements.code as Nro_proceso','pension_entities.name as Ente_gestor','affiliate_observations.date as Fecha_obs','affiliate_observations.message as Observacion')
                         // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
                          // ->orderBy('economic_complements.review_date','ASC')
                          ->get();



                        $sheet->fromArray($economic_complements);


                        });

                        $excel->sheet('Observación por Invalidez',function($sheet) {

                         global $com_obser_contabilidad_1,$com_obser_prestamos_2,$com_obser_juridica_3,$com_obser_fueraplz90_4,$com_obser_fueraplz120_5,$com_obser_faltareq_6,$com_obser_habitualinclusion7,$com_obser_menor16anos_8,$com_obser_invalidez_9,$com_obser_salario_10,$com_obser_pagodomicilio_12;  

                        
                         $economic_complements=EconomicComplement::whereIn('economic_complements.id',$com_obser_invalidez_9)
                          ->leftJoin('eco_com_applicants','economic_complements.id','=','eco_com_applicants.economic_complement_id')
                          ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                          ->leftJoin('eco_com_procedures','economic_complements.eco_com_procedure_id','=','eco_com_procedures.id')
                          ->leftJoin('cities','economic_complements.city_id','=','cities.id')
                          ->leftJoin('categories','economic_complements.category_id','=','categories.id')
                          ->leftJoin('base_wages','economic_complements.base_wage_id','=','base_wages.id')
                          ->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')

                          ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
                          ->leftJoin('degrees','affiliates.degree_id','=','degrees.id')
                          ->leftJoin('affiliate_observations','affiliates.id','=','affiliate_observations.affiliate_id')

                          // ->where('economic_complements.workflow_id','=','1')
                          // ->where('economic_complements.wf_current_state_id','2')
                          // ->where('economic_complements.state','Edited')
                          //  ->where('economic_complements.eco_com_procedure_id',$semestre->id)
                          // ->where('economic_complements.eco_com_procedure_id','2')

                          //->where('economic_complements.user_id',Auth::user()->id)

                          ->distinct('economic_complements.id')
                          ->select('economic_complements.id','eco_com_applicants.identity_card as CI','cities.first_shortened as Exp','eco_com_applicants.first_name as Primer_nombre','eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno','eco_com_applicants.mothers_last_name as Materno','eco_com_applicants.surname_husband as ap_esp','eco_com_applicants.birth_date as Fecha_nac','eco_com_applicants.nua','eco_com_applicants.phone_number as Telefono','eco_com_applicants.cell_phone_number as celular','eco_com_modalities.shortened as tipo_renta','eco_com_procedures.year as año_gestion','eco_com_procedures.semester as semestre','categories.name as categoria','degrees.shortened as Grado','economic_complements.total_rent as Renta_total','base_wages.amount as Sueldo_base','economic_complements.seniority as antiguedad','economic_complements.salary_quotable as Salario_cotizable','economic_complements.difference as direfencia','economic_complements.total_amount_semester as monto_total_semestre','economic_complements.complementary_factor as factor_de_complementacion','economic_complements.total','economic_complements.code as Nro_proceso','pension_entities.name as Ente_gestor','affiliate_observations.date as Fecha_obs','affiliate_observations.message as Observacion')
                         // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
                          // ->orderBy('economic_complements.review_date','ASC')
                          ->get();



                        $sheet->fromArray($economic_complements);


                        });

                        $excel->sheet('Observación por Salario',function($sheet) {

                         global $com_obser_contabilidad_1,$com_obser_prestamos_2,$com_obser_juridica_3,$com_obser_fueraplz90_4,$com_obser_fueraplz120_5,$com_obser_faltareq_6,$com_obser_habitualinclusion7,$com_obser_menor16anos_8,$com_obser_invalidez_9,$com_obser_salario_10,$com_obser_pagodomicilio_12;  

                        
                         $economic_complements=EconomicComplement::whereIn('economic_complements.id',$com_obser_salario_10)
                          ->leftJoin('eco_com_applicants','economic_complements.id','=','eco_com_applicants.economic_complement_id')
                          ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                          ->leftJoin('eco_com_procedures','economic_complements.eco_com_procedure_id','=','eco_com_procedures.id')
                          ->leftJoin('cities','economic_complements.city_id','=','cities.id')
                          ->leftJoin('categories','economic_complements.category_id','=','categories.id')
                          ->leftJoin('base_wages','economic_complements.base_wage_id','=','base_wages.id')
                          ->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')

                          ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
                          ->leftJoin('degrees','affiliates.degree_id','=','degrees.id')
                          ->leftJoin('affiliate_observations','affiliates.id','=','affiliate_observations.affiliate_id')

                          // ->where('economic_complements.workflow_id','=','1')
                          // ->where('economic_complements.wf_current_state_id','2')
                          // ->where('economic_complements.state','Edited')
                          //  ->where('economic_complements.eco_com_procedure_id',$semestre->id)
                          // ->where('economic_complements.eco_com_procedure_id','2')

                          //->where('economic_complements.user_id',Auth::user()->id)

                          ->distinct('economic_complements.id')
                          ->select('economic_complements.id','eco_com_applicants.identity_card as CI','cities.first_shortened as Exp','eco_com_applicants.first_name as Primer_nombre','eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno','eco_com_applicants.mothers_last_name as Materno','eco_com_applicants.surname_husband as ap_esp','eco_com_applicants.birth_date as Fecha_nac','eco_com_applicants.nua','eco_com_applicants.phone_number as Telefono','eco_com_applicants.cell_phone_number as celular','eco_com_modalities.shortened as tipo_renta','eco_com_procedures.year as año_gestion','eco_com_procedures.semester as semestre','categories.name as categoria','degrees.shortened as Grado','economic_complements.total_rent as Renta_total','base_wages.amount as Sueldo_base','economic_complements.seniority as antiguedad','economic_complements.salary_quotable as Salario_cotizable','economic_complements.difference as direfencia','economic_complements.total_amount_semester as monto_total_semestre','economic_complements.complementary_factor as factor_de_complementacion','economic_complements.total','economic_complements.code as Nro_proceso','pension_entities.name as Ente_gestor','affiliate_observations.date as Fecha_obs','affiliate_observations.message as Observacion')
                         // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
                          // ->orderBy('economic_complements.review_date','ASC')
                          ->get();



                        $sheet->fromArray($economic_complements);


                        });

                        $excel->sheet('Pago a domicilio',function($sheet) {

                         global $com_obser_contabilidad_1,$com_obser_prestamos_2,$com_obser_juridica_3,$com_obser_fueraplz90_4,$com_obser_fueraplz120_5,$com_obser_faltareq_6,$com_obser_habitualinclusion7,$com_obser_menor16anos_8,$com_obser_invalidez_9,$com_obser_salario_10,$com_obser_pagodomicilio_12;  

                        
                         $economic_complements=EconomicComplement::whereIn('economic_complements.id',$com_obser_pagodomicilio_12)
                          ->leftJoin('eco_com_applicants','economic_complements.id','=','eco_com_applicants.economic_complement_id')
                          ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                          ->leftJoin('eco_com_procedures','economic_complements.eco_com_procedure_id','=','eco_com_procedures.id')
                          ->leftJoin('cities','economic_complements.city_id','=','cities.id')
                          ->leftJoin('categories','economic_complements.category_id','=','categories.id')
                          ->leftJoin('base_wages','economic_complements.base_wage_id','=','base_wages.id')
                          ->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')

                          ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
                          ->leftJoin('degrees','affiliates.degree_id','=','degrees.id')
                          ->leftJoin('affiliate_observations','affiliates.id','=','affiliate_observations.affiliate_id')

                          // ->where('economic_complements.workflow_id','=','1')
                          // ->where('economic_complements.wf_current_state_id','2')
                          // ->where('economic_complements.state','Edited')
                          //  ->where('economic_complements.eco_com_procedure_id',$semestre->id)
                          // ->where('economic_complements.eco_com_procedure_id','2')

                          //->where('economic_complements.user_id',Auth::user()->id)

                          ->distinct('economic_complements.id')
                          ->select('economic_complements.id','eco_com_applicants.identity_card as CI','cities.first_shortened as Exp','eco_com_applicants.first_name as Primer_nombre','eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno','eco_com_applicants.mothers_last_name as Materno','eco_com_applicants.surname_husband as ap_esp','eco_com_applicants.birth_date as Fecha_nac','eco_com_applicants.nua','eco_com_applicants.phone_number as Telefono','eco_com_applicants.cell_phone_number as celular','eco_com_modalities.shortened as tipo_renta','eco_com_procedures.year as año_gestion','eco_com_procedures.semester as semestre','categories.name as categoria','degrees.shortened as Grado','economic_complements.total_rent as Renta_total','base_wages.amount as Sueldo_base','economic_complements.seniority as antiguedad','economic_complements.salary_quotable as Salario_cotizable','economic_complements.difference as direfencia','economic_complements.total_amount_semester as monto_total_semestre','economic_complements.complementary_factor as factor_de_complementacion','economic_complements.total','economic_complements.code as Nro_proceso','pension_entities.name as Ente_gestor','affiliate_observations.date as Fecha_obs','affiliate_observations.message as Observacion')
                         // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
                          // ->orderBy('economic_complements.review_date','ASC')
                          ->get();



                        $sheet->fromArray($economic_complements);


                        });


                })->download('xls');

        //return $economic_complements;
       // return "contribuciones totales ".$economic_complements->count();
      }
      else
      {
        return "funcion no disponible revise su sesion de usuario";
      }
    }

    public function create()
    {
        //
    }


    public function store(Request $request)
    {
        //
    }


    public function show($id)
    {
        //
    }


    public function edit($id)
    {
        //
    }


    public function update(Request $request, $id)
    {
        //
    }


    public function destroy($id)
    {
        //
    }
}
