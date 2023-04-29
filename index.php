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
    <style>
        div.header {
            background-color:#007CC5;
            height: 46px;
            line-height: 46px;
            text-align: middle;
            color:white;
            font-size:18px;
            font-family: 'Segoe UI';
            font-weight: 600;
            text-indent: 20px;
            padding-right: 20px;}
        img {
            position:relative;
            top:15%;}
        div.filter {
            background-color:#dee0e3;
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
                $destination_ip = $source_ip;}
            else {
                $source_ip = "";
                $destination_ip = "";}
            //port number conditional statement
            if(!empty($_POST["form_port_number"])) {
                $port_number = '{"port":'.$_POST["form_port_number"].'}';}
            else {
                $port_number = "";}
            //exclude transmission conditional statement
            if(isset($_POST['form_exclude_broadcast']) && isset($_POST['form_exclude_multicast'])) {
                $exclude_transmission='{"transmission":"broadcast"},{"transmission":"multicast"}';
            }
            elseif(isset($_POST['form_exclude_broadcast'])) {
                $exclude_transmission='{"transmission":"broadcast"}';
            }
            elseif(isset($_POST['form_exclude_multicast'])) {
                $exclude_transmission='{"transmission":"multicast"}';
            }
            else {$exclude_transmission="";}
            //exclude services conditional statement
            if(isset($_POST['form_exclude_services'])) {
                $port_exclusions = file_get_contents('port_exclusions.csv');
                $port_exclusions_array = str_getcsv($port_exclusions);
                $services_to_exclude="";
                foreach ($port_exclusions_array as $each_port) {
                    $services_to_exclude =  $services_to_exclude . '{"port":' . $each_port . '},';
                }
                $services_to_exclude = substr($services_to_exclude,0,-1);
                //$services_to_exclude='{"port":137},{"port":138},{"port":139},{"port":1900},{"port":3702},{"port":5353},{"port":5355}';
            } else {
                $services_to_exclude="";}
            //get date
            $now = date("Y-m-d\\TH:i:s");
            $then = date("Y-m-d\\TH:i:s", strtotime('-24 hours', time()));
            //query request body
            $body = <<<DATA
                {"sources":{"include":[[$source_ip]],"exclude":[]},"destinations":{"include":[[$destination_ip]],"exclude":[$exclude_transmission]},"services":{"include":[$port_number],"exclude":[$services_to_exclude]},"sources_destinations_query_op":"or","start_date":"$then","end_date":"$now","policy_decisions":["blocked"],"boundary_decisions":[],"max_results":100000,"exclude_workloads_from_ip_list_query":true,"query_name":""}
                DATA;
            //echo "Web request body:<br>";
            //echo "$body";
            //traffic flows async queries api call
            $ch = curl_init("https://$fqdn:$port/api/v2/orgs/1/traffic_flows/async_queries");
            curl_setopt($ch, CURLOPT_USERPWD, "$user:$key");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body );
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt($ch, CURLOPT_HEADER, false );
            curl_setopt($ch, CURLOPT_SUPPRESS_CONNECT_HEADERS, true );
            $result = curl_exec($ch);
            curl_close($ch);
            //get query href
            $traffic_flows_query_href = json_decode($result)->href;
            $traffic_flows_query_status="";
            //while loop until query completed
            while($traffic_flows_query_status != "completed"){
                sleep(1);
                $ch = curl_init("https://$fqdn:$port/api/v2$traffic_flows_query_href");
                curl_setopt($ch, CURLOPT_USERPWD, "$user:$key");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
                curl_setopt($ch, CURLOPT_HEADER, false );
                curl_setopt($ch, CURLOPT_SUPPRESS_CONNECT_HEADERS, true );
                $result = curl_exec($ch);
                curl_close($ch);
                $traffic_flows_query_status = json_decode($result)->status;}
            //download query href results
            $ch = curl_init("https://$fqdn:$port/api/v2$traffic_flows_query_href/download");
            curl_setopt($ch, CURLOPT_USERPWD, "$user:$key");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
            $row_count = 0;
            if(empty($_POST["form_ip"])) {
                echo "<table>";
                foreach(preg_split("/((\r?\n)|(\r\n?))/", $result) as $line){
                    $output = shell_exec("echo $line | cut -d, -f1,14,26,27,31,36,37");
                    if (!str_contains($output,",")) {continue;}
                    //echo "$output<br>";
                    if ($row_count == 0) {
                        echo "<tr>";
                        foreach(preg_split("/,/", $output) as $cell){
                            echo "<th>$cell</th>";}
                        echo "</tr>";
                        $row_count++;}
                    else {
                        echo "<tr>";
                        foreach(preg_split("/,/", $output) as $cell){
                            echo "<td>$cell</td>";}
                        echo "</tr>";}}
                echo "</table>";}
            else {
                //get header
                $header = preg_split("/((\r?\n)|(\r\n?))/", $result, 2)[0];
                $cut_header = shell_exec("echo $header | cut -d, -f1,14,26,27,31,36,37");
                foreach(preg_split("/((\r?\n)|(\r\n?))/", $result) as $line){
                    $line_result_consumer_ip = shell_exec("echo $line | cut -d, -f1 | xargs");
                    $line_result_consumer_ip = preg_replace('/\s+/', '', $line_result_consumer_ip);
                    if($line_result_consumer_ip == $_POST["form_ip"]) {
                        $cut_line = shell_exec("echo $line | cut -d, -f1,14,26,27,31,36,37");
                        $source_output = "$source_output\r\n$cut_line";}}
                echo "Source IP results:<br>";
                if (isset($source_output) && $source_output !== '') {
                    echo "<table>";
                    echo "<tr>";
                    foreach(preg_split("/,/", $cut_header) as $cell){
                        echo "<th>$cell</td>";}
                    echo "</tr>";
                    foreach(preg_split("/((\r?\n)|(\r\n?))/", $source_output) as $line){
                        if (!str_contains($line,",")) {continue;}
                        echo "<tr>";
                        foreach(preg_split("/,/", $line) as $cell){
                            echo "<td>$cell</td>";}
                        echo "</tr>";}
                    echo "<table>";}
                else {
                    echo "None<br>";}
                foreach(preg_split("/((\r?\n)|(\r\n?))/", $result) as $line){
                    $line_result_destination_ip = shell_exec("echo $line | cut -d, -f14 | xargs");
                    $line_result_destination_ip = preg_replace('/\s+/', '', $line_result_destination_ip);
                    if($line_result_destination_ip == $_POST["form_ip"]) {
                        $cut_line = shell_exec("echo $line | cut -d, -f1,14,26,27,31,36,37");
                        $destination_output = "$destination_output\r\n$cut_line";}}
                echo "<br>Destination IP results:<br>";
                if (isset($destination_output) && $destination_output !== '') {
                    echo "<table>";
                    echo "<tr>";
                    foreach(preg_split("/,/", $cut_header) as $cell){
                        echo "<th>$cell</td>";}
                    echo "</tr>";
                    foreach(preg_split("/((\r?\n)|(\r\n?))/", $destination_output) as $line){
                        if (!str_contains($line,",")) {continue;}
                        echo "<tr>";
                        foreach(preg_split("/,/", $line) as $cell){
                            echo "<td>$cell</td>";}
                        echo "</tr>";}
                    echo "<table>";}
                else {
                    echo "None<br>";}}}
    ?>
    <body style="margin: 0;padding: 0">
        <div class="header">Blocked Traffic Flows Query</div>
        <div class="filter">
            <form action="" method="post">
                IP: <input type="text" name="form_ip" value='<?php echo isset($_POST["form_ip"]) ? $_POST["form_ip"] : "" ?>'>&nbsp
                Port Number: <input type="text" name="form_port_number" value='<?php echo isset($_POST["form_port_number"]) ? $_POST["form_port_number"] : "" ?>'>&nbsp
                <input type="submit" name="submit"><br>
                <input type="checkbox" name="form_exclude_services" checked>Exclude ports: 
                <?php
                    $port_exclusions = file_get_contents('port_exclusions.csv');
                    echo $port_exclusions;
                ?>
                <input type="checkbox" name="form_exclude_broadcast" checked>Exclude broadcast 
                <input type="checkbox" name="form_exclude_multicast" checked>Exclude multicast 
            </form>
        </div>
        <div class="results">
            <?php
                ini_set('display_errors', '1');
                ini_set('display_startup_errors', '1');
                error_reporting(E_ALL);
                if(isset($_POST['submit'])){blocked_traffic_flows_query();}
            ?>
        </div>
    </body>
</html>
