<html>
    <!-- 
    Licensed under the Apache License, Version 2.0 (the "License"); you may not
    use this file except in compliance with the License. You may obtain a copy of
    the License at
        http:www.apache.org/licenses/LICENSE-2.0
    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
    WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
    License for the specific language governing permissions and limitations under
    the License.
    -->
    <head><meta http-equiv="content-type" content="text/html; charset=UTF-8"></head>
    <title>Illumio - Blocked Traffic Flows Query</title>
    <style>
        div.header {
            background-color: #007CC5;
            height: 46px;
            line-height: 46px;
            text-align: middle;
            color: white;
            font-size: 18px;
            font-family: 'Segoe UI';
            font-weight: 600;
            text-indent: 20px;
            padding-right: 20px;}
        span.info {
            float: right;
            font-size: 18px;
            font-family: 'Segoe UI';
            font-weight: 600;}
        a:link {
            font-weight: normal;}
        a:active {
            font-weight: normal;}
        a:visited {
            color: white;
            font-weight: normal;}
        a:hover {
            color: white;
            font-weight: normal;}
        img {
            position: relative;
            top: 15%;}
        div.filter {
            background-color: #e3e6e9;
            font-family: 'Segoe UI';
            color: #424242;
            height: 55px;
            padding-left: 20px;
            padding-top: 10px;}
        div.results {
            font-family: 'Segoe UI';
            padding-left: 20px;
            padding-top: 10px;}
        table, th, td {
            border: 1px solid black;
            border-collapse: collapse;
            padding: 5px;
            color: #424242;}
    </style>
    <?php
        function blocked_traffic_flows_query(){
            require_once 'config.php';
            $source_output = "";
            $destination_output = "";
            //ip address conditional statement
            if(!empty($_POST["form_ip"])) {
                $source_ip = '{"ip_address":"'.$_POST["form_ip"].'"}';
                $source_ip = str_replace(" ","",$source_ip);
                $destination_ip = $source_ip;}
            else {
                $source_ip = "";
                $destination_ip = "";}
            //port number conditional statement
            if(!empty($_POST["form_port_number"])) {
                $port_number = '{"port":'.$_POST["form_port_number"].'}';
                $port_number = str_replace(" ","",$port_number);}
            else {
                $port_number = "";}
            //exclude transmission conditional statement
            if(isset($_POST['form_exclude_broadcast']) && isset($_POST['form_exclude_multicast'])) {
                $exclude_transmission='{"transmission":"broadcast"},{"transmission":"multicast"}';}
            elseif(isset($_POST['form_exclude_broadcast'])) {
                $exclude_transmission='{"transmission":"broadcast"}';}
            elseif(isset($_POST['form_exclude_multicast'])) {
                $exclude_transmission='{"transmission":"multicast"}';}
            else {$exclude_transmission="";}
            //exclude services conditional statement
            if(isset($_POST['form_exclude_ports'])) {
                $port_exclusions = file_get_contents('port_exclusions.csv');
                $port_exclusions_array = str_getcsv($port_exclusions);
                $ports_to_exclude="";
                foreach ($port_exclusions_array as $each_port) {
                    $ports_to_exclude =  $ports_to_exclude . '{"port":' . $each_port . '},';
                }
                $ports_to_exclude = substr($ports_to_exclude,0,-1);}
            else {
                $ports_to_exclude="";}
            //exclude protocol conditional statement
            if(isset($_POST['form_exclude_protocols'])) {
                $protocol_exclusions = file_get_contents('protocol_exclusions.csv');
                $protocol_exclusions_array = str_getcsv($protocol_exclusions);
                $protocols_to_exclude="";
                foreach ($protocol_exclusions_array as $each_protocol) {
                    $protocols_to_exclude =  $protocols_to_exclude . '{"proto":' . $each_protocol . '},';
                }
                $protocols_to_exclude = substr($protocols_to_exclude,0,-1);}
            else {
                $protocols_to_exclude="";}
            //services_to_exclude
            if(!empty($ports_to_exclude) && !empty($protocols_to_exclude)){
                $services_to_exclude=$ports_to_exclude . "," . $protocols_to_exclude;}
            elseif(!empty($ports_to_exclude)){
                $services_to_exclude=$ports_to_exclude;}
            elseif(!empty($protocols_to_exclude)){
                $services_to_exclude=$protocols_to_exclude;}
            else {
                $services_to_exclude="";}
            //get date
            $now = date("Y-m-d\\TH:i:s");
            $then = date("Y-m-d\\TH:i:s", strtotime('-24 hours', time()));
            //query request body
            $body = <<<DATA
                {"sources":{"include":[[$source_ip]],"exclude":[]},"destinations":{"include":[[$destination_ip]],"exclude":[$exclude_transmission]},"services":{"include":[$port_number],"exclude":[$services_to_exclude]},"sources_destinations_query_op":"or","start_date":"$then","end_date":"$now","policy_decisions":["blocked"],"boundary_decisions":[],"max_results":100000,"exclude_workloads_from_ip_list_query":true,"query_name":""}
                DATA;
            echo "<br>Web request body:<br>";
            echo "$body";
            //traffic flows async queries api call
            $ch = curl_init("https://$fqdn:$port/api/v2/orgs/$org/traffic_flows/async_queries");
            curl_setopt($ch, CURLOPT_USERPWD, "$user:$key");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body );
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt($ch, CURLOPT_HEADER, false );
            curl_setopt($ch, CURLOPT_SUPPRESS_CONNECT_HEADERS, true );
            $result = curl_exec($ch);
            curl_close($ch);
            echo "<br>Web request response:<br>";
            echo "$result";
            //get query href
            $traffic_flows_query_href = json_decode($result)->href;
            $traffic_flows_query_status="";
            //while loop until query completed
            while($traffic_flows_query_status != "completed"){
                sleep(5);
                $ch = curl_init("https://$fqdn:$port/api/v2$traffic_flows_query_href");
                curl_setopt($ch, CURLOPT_USERPWD, "$user:$key");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
                curl_setopt($ch, CURLOPT_HEADER, false );
                curl_setopt($ch, CURLOPT_SUPPRESS_CONNECT_HEADERS, true );
                $result = curl_exec($ch);
                curl_close($ch);
                echo "<br>Web request response:<br>";
                echo "$result";
                $traffic_flows_query_status = json_decode($result)->status;}
            //download query href results
            $ch = curl_init("https://$fqdn:$port/api/v2$traffic_flows_query_href/download");
            curl_setopt($ch, CURLOPT_USERPWD, "$user:$key");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept:application/json'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
            echo "<br>Web request response:<br>";
            echo "$result";
            $flows = json_decode($result);
            //print_r($flows);
            if(empty($_POST["form_ip"])) {
                echo "<table>";
                echo "<tr><th>Source IP</th><th>Destination IP</th><th>Port</th><th>Protocol</th><th>Num Flows</th><th>First Detected</th><th>Last Detected</th></tr>";
                foreach($flows as $flow){
                    echo "<tr>";
                    echo "<td>"; print_r($flow->src->ip); echo "</td>";
                    echo "<td>"; print_r($flow->dst->ip); echo "</td>";
                    echo "<td>"; print_r($flow->service->port); echo "</td>";
                    if($flow->service->proto == "6") {echo "<td>TCP</td>";}
                    elseif($flow->service->proto == "17") {echo "<td>UDP</td>";}
                    elseif($flow->service->proto == "1") {echo "<td>ICMP</td>";}
                    else {echo "<td>"; print_r($flow->service->proto); echo "</td>";}
                    echo "<td>"; print_r($flow->num_connections); echo "</td>";
                    echo "<td>"; print_r($flow->timestamp_range->first_detected); echo "</td>";
                    echo "<td>"; print_r($flow->timestamp_range->last_detected); echo "</td>";
                    echo "</tr>";}
                echo "</table>";}
            else {
                $source_flows = array();
                foreach($flows as $flow){
                    $line_result_consumer_ip = $flow->src->ip;
                    if($line_result_consumer_ip == $_POST["form_ip"]) {
                        array_push($source_flows,$flow);}}
                echo "Source IP results:<br>";
                if (!empty($source_flows)){
                    echo "<table>";
                    echo "<tr><th>Source IP</th><th>Destination IP</th><th>Port</th><th>Protocol</th><th>Num Flows</th><th>First Detected</th><th>Last Detected</th></tr>";
                    foreach($source_flows as $flow){
                        echo "<tr>";
                        echo "<td>"; print_r($flow->src->ip); echo "</td>";
                        echo "<td>"; print_r($flow->dst->ip); echo "</td>";
                        echo "<td>"; print_r($flow->service->port); echo "</td>";
                        if($flow->service->proto == "6") {echo "<td>TCP</td>";}
                        elseif($flow->service->proto == "17") {echo "<td>UDP</td>";}
                        elseif($flow->service->proto == "1") {echo "<td>ICMP</td>";}
                        else {echo "<td>"; print_r($flow->service->proto); echo "</td>";}
                        echo "<td>"; print_r($flow->num_connections); echo "</td>";
                        echo "<td>"; print_r($flow->timestamp_range->first_detected); echo "</td>";
                        echo "<td>"; print_r($flow->timestamp_range->last_detected); echo "</td>";
                        echo "</tr>";}
                    echo "</table><br>";}
                else {
                    echo "None<br>";}
                $destination_flows = array();
                foreach($flows as $flow){
                    $line_result_destination_ip = $flow->dst->ip;
                    if($line_result_destination_ip == $_POST["form_ip"]) {
                        array_push($destination_flows,$flow);}}
                echo "Destination IP results:<br>";
                if (!empty($destination_flows)){
                    echo "<table>";
                    echo "<tr><th>Source IP</th><th>Destination IP</th><th>Port</th><th>Protocol</th><th>Num Flows</th><th>First Detected</th><th>Last Detected</th></tr>";
                    foreach($destination_flows as $flow){
                        echo "<tr>";
                        echo "<td>"; print_r($flow->src->ip); echo "</td>";
                        echo "<td>"; print_r($flow->dst->ip); echo "</td>";
                        echo "<td>"; print_r($flow->service->port); echo "</td>";
                        if($flow->service->proto == "6") {echo "<td>TCP</td>";}
                        elseif($flow->service->proto == "17") {echo "<td>UDP</td>";}
                        elseif($flow->service->proto == "1") {echo "<td>ICMP</td>";}
                        else {echo "<td>"; print_r($flow->service->proto); echo "</td>";}
                        echo "<td>"; print_r($flow->num_connections); echo "</td>";
                        echo "<td>"; print_r($flow->timestamp_range->first_detected); echo "</td>";
                        echo "<td>"; print_r($flow->timestamp_range->last_detected); echo "</td>";
                        echo "</tr>";}
                    echo "</table>";}
                else {
                    echo "None<br>";}}}
    ?>
    <body style="margin: 0;padding: 0">
        <div class="header">Illumio - Blocked Traffic Flows Query<span class="info"><a href="info.html" style="text-decoration:none;">?</a></span></div>
        <div class="filter">
            <form action="" method="post">
                IP: <input type="text" name="form_ip" value='<?php echo isset($_POST["form_ip"]) ? $_POST["form_ip"] : "" ?>'>&nbsp
                Port Number: <input type="text" name="form_port_number" value='<?php echo isset($_POST["form_port_number"]) ? $_POST["form_port_number"] : "" ?>'>&nbsp
                <input type="submit" name="submit"><br>
                <input type="checkbox" name="form_exclude_ports" checked>Exclude ports: 
                <?php
                    $port_exclusions = file_get_contents('port_exclusions.csv');
                    echo $port_exclusions;
                ?>
                <input type="checkbox" name="form_exclude_protocols" checked>Exclude protocols: 
                <?php
                    $protocol_exclusions = file_get_contents('protocol_exclusions.csv');
                    echo $protocol_exclusions;
                ?>
                <input type="checkbox" name="form_exclude_broadcast" checked>Exclude broadcast 
                <input type="checkbox" name="form_exclude_multicast" checked>Exclude multicast 
            </form>
        </div>
        <div class="results">
            <?php
                ini_set('display_errors', '1');
                ini_set('display_startup_errors', '1');
                ini_set("memory_limit", "1024M");
                error_reporting(E_ALL);
                if(isset($_POST['submit'])){blocked_traffic_flows_query();}
            ?>
        </div>
    </body>
</html>
