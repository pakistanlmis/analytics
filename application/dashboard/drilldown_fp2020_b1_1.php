<?php
//echo '<pre>';print_r($_REQUEST);exit;
ini_set('max_execution_time', 0);
//Including files
include("../includes/classes/Configuration.inc.php");
include(APP_PATH."includes/classes/db.php");

include(PUBLIC_PATH . "html/header.php");
include APP_PATH."includes/classes/functions.php";
//include(PUBLIC_PATH . "html/header.php");
require(PUBLIC_PATH."FusionCharts/Code/PHP/includes/FusionCharts.php");

$subCaption='';

$prov_name= $_REQUEST['prov_name'];
$province = $_REQUEST['province'];
$from_date = date("Y-m-d", strtotime($_REQUEST['from_date']));
//$to_date = date("Y-m-d", strtotime($_REQUEST['to_date']));
$stakeholder = $_REQUEST['stakeholder'];
$stk_name = $_REQUEST['stk_name'];
$products = $_REQUEST['products'];

$province_param = 'all';
$province_param = (($province!='all')?$province:'\'all\'');
$province_name_param = 'All';
$province_name_param = (($province!='all')?$prov_name:'All');
        

//Chart caption
$caption = 'Stock Availability Rate at Districts of '.$prov_name.' - '.$stk_name.' for '.date('M-Y',strtotime($from_date));
//Chart heading sub Caption

//download File Name
$downloadFileName = $caption . ' - ' . date('Y-m-d H:i:s');
//chart_id
$chart_id = 'b3';

?>
<?php

include PUBLIC_PATH . "html/top_im.php";
?>
<link href="<?php echo PUBLIC_URL;?>assets/global/plugins/font-awesome/css/font-awesome.min.css" rel="stylesheet" />
<script language="Javascript" src="<?php echo PUBLIC_URL; ?>FusionCharts/Charts/FusionCharts.js"></script>
    <script language="Javascript" src="<?php echo PUBLIC_URL; ?>FusionCharts/themes/fusioncharts.theme.fint.js"></script>
<div class="widget widget-tabs">
    <div class="widget-body">
    <a href="javascript:exportChart('<?php echo $chart_id;?>', '<?php echo $downloadFileName;?>')" style="float:right;display:none;"><img class="export_excel" src="<?php echo PUBLIC_URL;?>images/excel-16.png" alt="Export" /></a>
	<?php 
        //query for total districts of the stakeholder
         $qry_dist ="SELECT 
                    count( DISTINCT tbl_warehouse.dist_id ) as total_districts

                    FROM
                            tbl_warehouse
                    /*INNER JOIN wh_user ON tbl_warehouse.wh_id = wh_user.wh_id
                    INNER JOIN sysuser_tab ON wh_user.sysusrrec_id = sysuser_tab.UserID*/
                    INNER JOIN stakeholder ON tbl_warehouse.stkofficeid = stakeholder.stkid
                    INNER JOIN tbl_locations ON tbl_warehouse.dist_id = tbl_locations.PkLocID
                    INNER JOIN stakeholder AS mainStk ON tbl_warehouse.stkid = mainStk.stkid
                    WHERE
                            stakeholder.lvl = 3 AND
                            tbl_warehouse.stkid = $stakeholder ";
         if($province!= 'all')
         $qry_dist .= " AND tbl_warehouse.prov_id = $province";
         
        $res_dist= mysql_query($qry_dist);
        $row = mysql_fetch_assoc($res_dist);
        $total_districts = $row['total_districts'];
        //Query for shipment main dashboard
  $qry = "
            SELECT
              summary_district.item_id,
              count( DISTINCT summary_district.district_id ) AS reported_districts,
	      SUM( CASE WHEN (summary_district.soh_district_store / summary_district.avg_consumption ) > 0 THEN 1 ELSE 0 END ) as nmbr_of_districts,
              itminfo_tab.itm_name
            FROM
                      summary_district
              INNER JOIN tbl_locations ON summary_district.district_id = tbl_locations.PkLocID
              INNER JOIN tbl_locations AS Province ON tbl_locations.ParentID = Province.PkLocID
              INNER JOIN stakeholder ON summary_district.stakeholder_id = stakeholder.stkid
              INNER JOIN itminfo_tab ON summary_district.item_id = itminfo_tab.itmrec_id
            WHERE
              summary_district.reporting_date = '".$from_date."' AND
              stakeholder.stkid = $stakeholder
              AND itminfo_tab.itm_category = 1
              ";
    if(empty($products) || $products == 'null') 
           $qry .= " AND itminfo_tab.itm_id NOT IN(4,6,10,33) ";
    else
           $qry .= " AND itminfo_tab.itm_id IN(".$products.") ";
    if($province!= 'all')
            $qry .= "  AND Province.PkLocID = $province ";
    
        $qry .= " 
            GROUP BY 
          summary_district.item_id
 ";
    //echo $qry;exit;
    $qryRes = mysql_query($qry);
    $c=1;
     $itm_arr = $reported_arr  = $so_arr = array();
    
    while($row = mysql_fetch_assoc($qryRes))
    {
        $itm_arr[$row['item_id']] = $row['itm_name'];
        //$reported_arr[$row['item_id']] = $row['reported_districts'];
        $so_arr[$row['item_id']] = $row['nmbr_of_districts'];
    }  
    
    $qry = "SELECT
                Count(distinct tbl_warehouse.wh_id) as dist_stores_reported,
                tbl_wh_data.item_id
            FROM
                tbl_wh_data
            INNER JOIN tbl_warehouse ON tbl_wh_data.wh_id = tbl_warehouse.wh_id
            INNER JOIN stakeholder ON tbl_warehouse.stkofficeid = stakeholder.stkid
            INNER JOIN itminfo_tab ON tbl_wh_data.item_id = itminfo_tab.itmrec_id
            WHERE
                 
                tbl_warehouse.stkid = $stakeholder AND
                tbl_wh_data.RptDate = '".$from_date."' AND
                stakeholder.lvl = 3 AND 
                itminfo_tab.itm_category = 1
              ";
            if(empty($products) || $products == 'null') 
                   $qry .= " AND itminfo_tab.itm_id NOT IN(4,6,10,33) ";
            else
                   $qry .= " AND itminfo_tab.itm_id IN(".$products.") ";
            if($province!= 'all')
                    $qry .= "  AND tbl_warehouse.prov_id = $province ";

                $qry .= " 
            GROUP BY
            tbl_wh_data.item_id
            ";
    $qryRes = mysql_query($qry);
    while($row = mysql_fetch_assoc($qryRes))
    {
        $reported_arr[$row['item_id']] = $row['dist_stores_reported'];
    }  
    
    
    //echo '<pre>';print_r($so_arr);exit;
    $r_arr =$reverse_arr = $stake_wise_arr =array();
  
    $temp = $prods = 0;
    foreach($so_arr as $itm_id => $val)
    {
         
            $prods++;
            if(!empty($reported_arr[$itm_id]) && $reported_arr[$itm_id]>0)
                $perc = ((!empty($val)?$val:0)* 100)/$reported_arr[$itm_id];
            else
                $perc=0;
            
            $r_arr[$itm_id] = $perc;
            
           
    }
    //echo '<pre>'.$total_districts;print_r($r_arr);print_r($so_arr);print_r($itm_arr);exit;    
        
    //xml for chart
    $xmlstore = '<chart caption="'.$caption.'" yaxismaxvalue="100"  subcaption="Product Wise" xaxisname="Products" yaxisname="Percentage" numberprefix=""  palettecolors="#26C281,#FF0000,#53A5E0"  exportEnabled="1" theme="fint">';
        
    $xmlstore .= ' <categories>';
   foreach($so_arr as $itm_id => $val)
    {
        $name = (isset($itm_arr[$itm_id])?$itm_arr[$itm_id]:'0');
         $xmlstore .= '     <category label="'.$name.'"/>';  
    }
    $xmlstore .= ' </categories>';
    
        $xmlstore .= ' <dataset  seriesname="Stock Availability Rate"  showvalues="1">';
            foreach($itm_arr as $id => $name)
            {
                if(!empty($reported_arr[$itm_id]) && $reported_arr[$itm_id]>0)
                    $t_rep = $reported_arr[$itm_id];

                $t_rep = (isset($reported_arr[$id])?$reported_arr[$id]:0);
                $t_rep2 = (isset($so_arr[$id])?$so_arr[$id]:0);

                $val = (isset($r_arr[$id])?$r_arr[$id]:0);
                $xmlstore .= '     <set label="'.$name.'" tooltext="Stock available at '.$t_rep2.' out of '.$t_rep.' reported districts" value="'.(number_format(!empty($val)?$val:0,2)).'" link="JavaScript:showDrillDown_lvl2('.$province_param.',\''.$province_name_param.'\',\''.$from_date.'\','.$stakeholder.',\''.$stk_name.'\',\''.$id.'\',\''.$name.'\');"  />';
            }

        $xmlstore .= '    </dataset>';
    
        $xmlstore .= ' <dataset  seriesname="Reporting Rate (District Stores)"  renderas="line" showvalues="1">';
             foreach($so_arr as $itm_id => $val)
            {
                $rep = (isset($reported_arr[$itm_id])?$reported_arr[$itm_id]:'0');
                $tot = $total_districts;
                if(isset($tot) && $tot>0) $val = $rep * 100 / $tot;
                else $val = 0;

                $xmlstore .= '     <set label="'.$name.'" value="'.(number_format(!empty($val)?$val:0,1)).'"  />';
            }
        $xmlstore .= '  </dataset>';
    $xmlstore .= ' </chart>';
    //end chart
   
    //Render chart
    FC_SetRenderer('javascript');
    echo renderChart(PUBLIC_URL."FusionCharts/Charts/StackedColumn2DLine.swf", "", $xmlstore, $chart_id, '100%', 330, false, false);
    ?>
	</div>
</div>
    
    <div class="widget widget-tabs">    
        <div class="widget-body" id="drilldown_div_2">
            
        </div>
    </div>
    
<script src="<?php echo PUBLIC_URL;?>assets/global/plugins/jquery-1.11.0.min.js" type="text/javascript"></script>
<script src="<?php echo PUBLIC_URL;?>assets/global/plugins/bootstrap-modal/js/bootstrap-modal.js" type="text/javascript"></script>
<script src="<?php echo PUBLIC_URL;?>assets/global/plugins/bootstrap-modal/js/bootstrap-modalmanager.js" type="text/javascript"></script>
    <script>

    $(document).on('click', '.legend_block', function(){
        var clr = $(this).data('clr');
        //alert(clr);
        $('.t_rows').hide();
        $('.t_rows[data-clr='+clr+']').show();
        $('#show_all').show();
        
    });
    $(document).on('click', '#show_all', function(){
        $('.t_rows').show();
    });
    function showDrillDown_lvl2(prov,prov_name,from_date,stk,stk_name,prod_id,prod_name) {
        //window.open("drilldown_stk_1.php?province="+prov+"&prov_name="+prov_name+"&from_date="+from_date+"&stakeholder="+stk+"&stk_name="+stk_name ,"", "width=800,height=700");
        
        var url = 'drilldown_fp2020_b1_2.php';
        
        var dataStr='';
        dataStr += "province="+prov+"&prov_name="+prov_name+"&from_date="+from_date+"&stakeholder="+stk+"&stk_name="+stk_name+"&prod_id="+prod_id+"&prod_name="+prod_name;

        $('#drilldown_div_2').html("<center><div id='loadingmessage'><img src='<?php echo PUBLIC_URL; ?>images/ajax-loader.gif'/></div></center>");

        $.ajax({
            type: "POST",
            url: '<?php echo APP_URL; ?>dashboard/' + url,
            data: dataStr,
            dataType: 'html',
            success: function(data) {
                    $("#drilldown_div_2").html(data);
            }
        });
    
    }
    </script>

