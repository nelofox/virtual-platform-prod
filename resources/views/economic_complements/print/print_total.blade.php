@extends('globalprint.wkhtml')
@section('title2')
  <center><strong>{{ $title2 }} @if ($economic_complement->old_eco_com && $economic_complement->total_repay > 0)
     (REINTEGRO)
   @endif</strong></center>
@endsection
@section('content')
<style type="text/css">
    .number{
      text-align: right;
    }
    td{
      font-size:13px;
    }
    .p-19{
      padding: 0px 19px;
    }
</style>
    @section('padding')
    p-19
    @endsection
<div class="main1 p-19">
<div class="title2"><strong class="code">{!! $title_inline !!}</strong><strong class="code">DOC - {!! $doc_number !!} </strong><strong class="code">Trámite Nº: {!! $economic_complement->code !!} </strong></div>
  <div id="project">
    @include('economic_complements.info.applicant_info',['eco_com_applicant'=>$eco_com_applicant])
    @if($economic_complement->has_legal_guardian)
    @include('economic_complements.info.legal_guardian',['economic_complement_legal_guardian'=>$economic_complement_legal_guardian, 'economic_complement'=>$economic_complement])
    @endif

    @include('economic_complements.info.simple_info',['economic_complement'=>$economic_complement])
    <br>
    <table>
      <tr>
        <td colspan="3" class="grand service info_title" ><strong>CÁLCULO DEL COMPLEMENTO ECONÓMICO {{ $economic_complement->economic_complement_procedure->getShortenedNameTwo() }}</strong></td>
      </tr>
      <tr>
        <td class="grand service info_title" rowspan="2" style="vertical-align: middle;"><strong>DETALLE</strong></td>
        <td class="grand service info_title" colspan="2"><b style="text-align: center">MONTO CALCULADO</strong></td>
        </tr>
        <tr>
          <td class="grand service"><strong>A FAVOR</strong></td><td class="grand service"><strong>DESCUENTO</strong></td>
        </tr>
        <tr>
          <td><strong>RENTA O PENSIÓN (PASIVO NETO)</strong></td><td class="number"><strong>{{$total_rent}}</strong></td><td></td>
        </tr>
        <tr>
          <td>REFERENTE DE CALIFICACIÓN (PROMEDIO)</td><td class="number">{{($total_rent_calc == $total_rent) ? NULL : $total_rent_calc }}</td><td></td>
        </tr>
        <tr>
          <td>REFERENTE SALARIO DEL ACTIVO</td><td class="number">{{$salary_reference}}</td><td></td>
        </tr>
        <tr>
          <td>ANTIGÜEDAD (SEGÚN CATEGORÍA)</td><td class="number">{{$seniority}}</td><td></td>
        </tr>
        <tr>
          <td>SALARIO COTIZABLE (SALARIO DEL ACTIVO + ANTIGÜEDAD)</td><td class="number">{{$salary_quotable}}</td><td></td>
        </tr>
        <tr>
          <td>DIFERENCIA (SALARIO ACTIVO - RENTA PASIVO)</td><td class="number">{{$difference}}</td><td></td>
        </tr>
        <tr>
          <td>TOTAL SEMESTRE (DIFERENCIA SE MULTIPLICA POR 6 MESES)</td><td class="number">{{$total_amount_semester}}</td><td></td>
        </tr>
        <tr>
          <td>FACTOR DE COMPLEMENTACIÓN</td><td class="number">{{ intval($economic_complement->complementary_factor) }} %</td><td></td>
        </tr>
        @if($economic_complement->amount_loan  > 0 || $economic_complement->amount_accounting > 0|| $economic_complement->amount_replacement >0 )
        <tr>
          <td class="grand service text-left"><strong>TOTAL COMPLEMENTO ECONÓMICO EN BOLIVIANOS {{ $economic_complement->old_eco_com ? '(RECALIFICADO)':'' }}</strong></td><td class="number"><strong>{{Util::formatMoney($temp_total)}}</strong></td><td></td>
        </tr>
        @endif
        @if($economic_complement->amount_loan)
        <tr>
          <td> – AMORTIZACIÓN POR PRESTAMOS EN MORA</td><td></td><td class="number" >{{Util::formatMoney($economic_complement->amount_loan)}}</td>
        </tr>
        @endif
        @if($economic_complement->amount_accounting)
        <tr>
          <td> – AMORTIZACIÓN POR CUENTAS POR COBRAR</td><td></td><td class="number" >{{Util::formatMoney($economic_complement->amount_accounting)}}</td>
        </tr>
        @endif
        @if($economic_complement->amount_replacement)
        <tr>
          @if($economic_complement->affiliate->devolution->first())
          <td> – AMORTIZACIÓN POR REPOSICIÓN DE FONDOS {{ $economic_complement->affiliate->devolution->first()->percentage ? ' CON EL '.($economic_complement->affiliate->devolution->first()->percentage*100).'%' : 'POR EL TOTAL' }}</td><td></td><td class="number" >{{Util::formatMoney($economic_complement->amount_replacement)}}</td>
          @else
          <td> – AMORTIZACIÓN POR REPOSICIÓN DE FONDOS</td><td></td><td class="number" >{{Util::formatMoney($economic_complement->amount_replacement)}}</td>
          @endif
        </tr>
        @endif
        <tr>
          @if($economic_complement->amount_loan  > 0 || $economic_complement->amount_accounting > 0|| $economic_complement->amount_replacement >0 )
          <td class="grand service text-left"><strong>TOTAL LIQUIDO A PAGAR EN BOLIVIANOS</strong></td><td class="number"><strong>{{$total}}</strong></td><td></td>
          @else
          <td class="grand service text-left"><strong>TOTAL COMPLEMENTO ECONÓMICO EN BOLIVIANOS{{-- {{ $economic_complement->economic_complement_procedure->getShortenedNameTwo() }} --}}</strong></td><td class="number"><strong>{{$total}}</strong></td><td></td>
          @endif
        </tr>
        @if($economic_complement->old_eco_com)
          @if ($economic_complement->eco_com_state_id != 15)
            <tr>
              <td>TOTAL COMP. ECO. PAGADO</td>
              <td class="number">{!! Util::formatMoney($old_eco_com_total_calificate) !!}</td>
              <td></td>
            </tr>
            <tr style="font-size: 1.1em">
              <td  class="grand service text-left">TOTAL REINTEGRO</td>
              <td class="number"><strong>{!! Util::formatMoney($economic_complement->total_repay) !!}</strong></td>
              <td></td>
            </tr>
            <tr>
              <td colspan="3"><strong>Son: </strong> {{ Util::convertir($economic_complement->total_repay) }} BOLIVIANOS.</td>
            </tr>
          @else
            <tr>
              <td>TOTAL COMP. ECO. (PASIVO DIFERIDO)</td>
              <td class="number">{!! Util::formatMoney($old_eco_com_total_calificate) !!}</td>
              <td></td>
            </tr>
            <tr>
              <td class="text-left">TOTAL REINTEGRO</td>
              <td class="number">{!! Util::formatMoney($economic_complement->total_repay) !!}</td>
              <td></td>
            </tr>
              <tr style="font-size: 1.1em">
              <td class="grand service text-left">TOTAL A PAGAR</td>
              <td class="number"><strong>{!! Util::formatMoney($economic_complement->total) !!}</strong></td>
              <td></td>
            </tr>
            <tr>
              <td colspan="3"><strong>Son: </strong> {{ Util::convertir($economic_complement->total) }} BOLIVIANOS.</td>
            </tr>
          @endif
        @else
        <tr>
          <td colspan="3"><strong>Son: </strong> {{ $total_literal }} BOLIVIANOS.</td>
        </tr>
        @endif
        </table>
        <table>
        <tr>
          <td style="width:30px;" class="grand service text-left">NOTA:</td>
          <td ><strong> </strong>{!!$economic_complement->comment!!}</td>
        </tr>
      </table>
      <br>
      <table>
        <tr>
          <td class="width-20-por no-border"></td>
          <td class="width-30-por padding-top"><strong>Elaborado y Revisado por:</strong></td>
          {{-- <td class="width-30-por padding-top"><strong>Aprobado por:</strong></td> --}}
          <td class="width-30-por padding-top"><strong>V° B°</strong></td>
          <td class="width-20-por no-border"></td>
        </tr>
      </table>
  </div>
  <br>
  {{-- <div class="hr-line"></div> --}}
{{-- backrest --}}
{{-- <table class="tableh">
  <tr>
    <th style="width: 25%;border: 0px;">
      <div id="logo">
        <img src="{{ asset('img/logo.jpg') }}" >
      </div>
    </th>
    <th style="width: 50%;border: 0px">
      <h4><b>MUTUAL DE SERVICIOS AL POLICÍA<br>
        {!! $header1 !!}<br>{!! $header2 !!}
        @yield('title')
      </b></h4>
    </th>
    <th style="width: 25%;border: 0px">
      <div id="logo2">
        <img src="{{ asset('img/escudo.jpg') }}" >
      </div>
    </th>
  </tr>
</table> --}}
  {{-- <table >
    <tr>
      <td class="izq no-border size-10">
        <strong>Fecha de Emisi&#243n: </strong> {!! $date !!} - {!! $hour !!}    
      </td>
      <td class="der no-border size-10">
        @if(isset($user))
          <strong>Usuario: </strong>{!! $user->username !!} - {!! $user_role !!}
        @endif
      </td>
    </tr>
  </table>

  <div class="title2 size-12"><strong class="code">COMPLEMENTO ECONÓMICO</strong><strong class="code">DOC - {!! $doc_number !!} </strong><strong class="code">Trámite Nº: {!! $economic_complement->code !!} </strong></div>
  <div id="project">  
    @include('economic_complements.info.applicant_info',['eco_com_applicant'=>$eco_com_applicant])

    @if($economic_complement->has_legal_guardian)
    @include('economic_complements.info.legal_guardian',['economic_complement_legal_guardian'=>$economic_complement_legal_guardian])
    @endif

    @include('economic_complements.info.simple_info',['economic_complement'=>$economic_complement])

    <table>
      <tr>
        <td class="grand service text-left"><strong>TOTAL LIQUIDO A PAGAR EN BS.</strong></td><td class="number" rowspan="2"><strong class="size-16">{{$total}}</strong></td>
      </tr>
      <tr>
        <td><strong>Son: </strong> {{ $total_literal }} Bolivianos</td>
        
      </tr>
    </table>
  </div> --}}
</div>
<div style="margin:0; padding:0; width:100%; display:block">
  <div class="main-left">
    <table>
      <tr>
        <td colspan="2" class="no-border"></td>
      </tr>
      <tr class="tableh1">
        <th colspan="2" style="width: 50%;border: 0px;padding-top:10px" class="size-8">
          <b>MUTUAL DE SERVICIOS AL POLICÍA<br>
            {!! $header1 !!}<br>{!! $header2 !!}
            @yield('title')
            <br> <em>"{{ strtoupper($economic_complement->economic_complement_procedure->getFullName() ?? '') }}
              @if ($economic_complement->old_eco_com && $economic_complement->total_repay > 0)
              (REINTEGRO)
              @endif
              "</em>
          </b>
        </th>
      </tr>
      <tr>
        <td colspan="2" class="no-border">
          <em>{{ ucwords(strtolower($user->city->name ?? '')) ?? 'La Paz' }}, {!! $date !!} - {!! $hour !!}</em>
        </td>
      </tr>
      <tr>
        <td colspan="2" class="no-border">
          <strong><em>REGIONAL:</em></strong>  {{ $economic_complement->city->name ?? '' }} <br>
        </td>
      </tr>
      <tr>
        <td colspan="2" class="no-border">
          <strong><em>GRADO:</em></strong>  {{ $economic_complement->degree->shortened ?? '' }} <br>
        </td>
      </tr>
      <tr>
        <td colspan="2" class="no-border">
          <strong><em>CATEGORÍA:</em></strong>  {{ $economic_complement->category->name ?? '' }} <br>
        </td>
      </tr>
      <tr>
        <td colspan="2" class="no-border">
          <strong><em>NOMBRES Y APELLIDOS:</em></strong><br>
            {{ $economic_complement->economic_complement_applicant->getFullName() ?? '' }} <br>
        </td>
      </tr>
      <tr>
        <td colspan="2" class="no-border">
          <strong><em>C.I.:</em></strong> {!! $economic_complement->economic_complement_applicant->identity_card !!} {{$economic_complement->economic_complement_applicant->city_identity_card ? $economic_complement->economic_complement_applicant->city_identity_card->first_shortened.'.' : ''}} <br>
        </td>
      </tr>
      <tr>
        <td colspan="2" class="no-border">
          <strong><em>TRÁMITE Nº:</em></strong> {!! $economic_complement->code ?? ''!!} <br>
        </td>
      </tr>
      <tr>
        <td colspan="2" class="no-border">
          @if ($economic_complement->old_eco_com)
            @if ($economic_complement->eco_com_state_id != 15)
            <strong>SON:</strong>
            <em class="size-9">{{ Util::convertir($economic_complement->total_repay ?? 0 )   }} BOLIVIANOS.</em>
            @else
            <strong>SON:</strong>
            <em class="size-9">{{ Util::convertir($economic_complement->total ?? 0 )   }} BOLIVIANOS.</em>
            @endif
          @else
            <strong>SON:</strong>
            <em class="size-9">{{ Util::convertir($temp_total > 0 ? $temp_total : $economic_complement->total)   }} BOLIVIANOS.</em>
          @endif
        </td>
      </tr>
      <tr>
        <td class="no-border" >
          {{-- <strong>TIPO DE PRESTACIÓN:</strong> --}}
          {{ strtoupper($economic_complement->economic_complement_modality->economic_complement_type->name) ?? '' }}
        </td>
        <td class="no-border text-center size-16">
          <span class="code border-radius">
            @if ($economic_complement->old_eco_com)
              @if ($economic_complement->eco_com_state_id != 15)
              Bs. {{ Util::formatMoney($economic_complement->total_repay ?? 0 ) }}
              @else
              Bs. {{ Util::formatMoney($economic_complement->total ?? 0 ) }}
              @endif
            @else
              Bs. {{ Util::formatMoney($temp_total > 0 ? $temp_total : $economic_complement->total ) }}
            @endif
          </span>
        </td>
      </tr>
    </table>
    {{-- <span style="position: absolute; bottom:0%" class="size-6">PLATAFORMA VIRTUAL DE LA MUSERPOL - 2018</span> --}}
      
  </div>
  <div class="main-right">
    <table>
      <tr>
        <td colspan="4" class="no-border"></td>
      </tr>
      <tr class="tableh1">
        <th colspan="4" style="width: 50%;border: 0px;padding-top:10px" class="size-8">
          <b>MUTUAL DE SERVICIOS AL POLICÍA<br>
            {!! $header1 !!}<br>{!! $header2 !!}
            @yield('title')
            <br> <em>"{{ strtoupper($economic_complement->economic_complement_procedure->getFullName() ?? '') }}
              @if ($economic_complement->old_eco_com && $economic_complement->total_repay > 0)                
              (REINTEGRO)
              @endif
            "</em>
          </b>
        </th>

      </tr>
      <tr >
        <td class="no-border" colspan="4">
          <em>{{ ucwords(strtolower($user->city->name ?? '')) ?? 'La Paz' }}, {!! $date !!} - {!! $hour !!}</em>
        </td>
      </tr><tr>
        <td colspan="2" class="no-border">
          <strong><em>REGIONAL:</em></strong>  {{ $economic_complement->city->name ?? '' }} <br>
          <strong><em>C.I.:</em></strong> {!! $economic_complement->economic_complement_applicant->identity_card !!} {{$economic_complement->economic_complement_applicant->city_identity_card ? $economic_complement->economic_complement_applicant->city_identity_card->first_shortened.'.' : ''}} <br>
          <strong><em>TRÁMITE Nº:</em></strong> {!! $economic_complement->code ?? ''!!} <br>
        </td>
          <td colspan="2" class="text-center no-border">
          <span >
            {{ strtoupper($economic_complement->economic_complement_modality->economic_complement_type->name) ?? '' }}
          </span>
          <br>
          <br>
          @if ($economic_complement->old_eco_com)
            @if ($economic_complement->eco_com_state_id != 15)
              <strong class="code border-radius size-16 ">Bs. {{ Util::formatMoney($economic_complement->total_repay ?? 0 ) }}</strong>
            @else
              <strong class="code border-radius size-16 ">Bs. {{ Util::formatMoney($economic_complement->total ?? 0 ) }}</strong>
            @endif
          @else
            <strong class="code border-radius size-16 ">Bs. {{ Util::formatMoney($temp_total > 0 ? $temp_total : $economic_complement->total) }}</strong>
          @endif
        </td>
          <tr><td colspan="4" class="no-border">
          <strong><em>PÁGUESE A LA ORDEN DE:</em></strong><br>
            <span class="margin-l-10">{{ $economic_complement->economic_complement_applicant->getFullName() ?? '' }} </span><br>
        </td>
        
      </tr>
      <tr>
        <td colspan="4" class="no-border">
          <strong>LA SUMA DE:</strong><br>
          @if ($economic_complement->old_eco_com)
            @if ($economic_complement->eco_com_state_id != 15)
              <em class="size-10">{{ Util::convertir($economic_complement->total_repay ?? 0)   }} BOLIVIANOS.</em>
            @else
              <em class="size-10">{{ Util::convertir($economic_complement->total ?? 0)   }} BOLIVIANOS.</em>
            @endif
          @else
            <em class="size-10">{{ Util::convertir($temp_total > 0 ? $temp_total : $economic_complement->total)   }} BOLIVIANOS.</em>
          @endif
        </td>
      </tr>
      <tr>
        <td class="width-30-por no-border text-center">
          <div class="code border-radius">{{ $economic_complement->degree->shortened ?? '' }} <br> <em>GRADO</em></div>
        </td>
        <td class="width-20-por no-border text-center">
          <div class="code border-radius">{{ $economic_complement->category->name ?? '' }} <br> <em>CATEGORÍA</em></div>
        </td>
        <td class="width-20-por no-border text-center">
          <div class="code border-radius">6<br> <em>MESES</em></div>
        </td>
        <td class="width-30-por no-border text-center">
          @if ($economic_complement->old_eco_com)
            @if ($economic_complement->eco_com_state_id != 15)
              <div class="code border-radius"><strong>{{ Util::formatMoney($economic_complement->total_repay ?? 0) ?? '' }}</strong> <br> <em>LIQUIDO PAGABLE</em></div>
            @else
              <div class="code border-radius"><strong>{{ Util::formatMoney($economic_complement->total ?? 0) ?? '' }}</strong> <br> <em>LIQUIDO PAGABLE</em></div>
            @endif
          @else
            <div class="code border-radius"><strong>{{ Util::formatMoney($temp_total > 0 ? $temp_total : $economic_complement->total) ?? '' }}</strong> <br> <em>LIQUIDO PAGABLE</em></div>
          @endif
          {{-- <span class="code border-radius">Bs. {{ Util::formatMoney($economic_complement->total) }}</span> --}}
        </td>
      </tr>
      <tr>
        <td class="no-border"></td>
      </tr><tr>
        <td class="no-border"></td>
      </tr><tr>
        <td class="no-border"></td>
      </tr><tr>
        <td class="no-border"></td>
      </tr>
    </table>
    {{-- <span style="position: absolute; bottom:0%" class="size-6">PLATAFORMA VIRTUAL DE LA MUSERPOL - 2018</span> --}}
  </div>
</div>
@endsection
