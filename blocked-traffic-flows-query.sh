#!/bin/bash
#
#blocked-traffic-flows-query.sh
version="0.0.1"
#
#Licensed under the Apache License, Version 2.0 (the "License"); you may not
#use this file except in compliance with the License. You may obtain a copy of
#the License at
#
#    http://www.apache.org/licenses/LICENSE-2.0
#
#Unless required by applicable law or agreed to in writing, software
#distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
#WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
#License for the specific language governing permissions and limitations under
#the License.
#

usage(){
    cat << EOF
blocked-traffic-flows-query.sh - sends a traffic flows query for blocked traffic
default time filter is within the last 24 hours

dependencies: jq, python, pandas python package

usage: ./blocked-traffic-flows-query.sh [options]

options:
    -i, --ip <ip address>
    -d, --direction <source or dest>
    -p, --port_number <int>
    -e, --exclude_services <true or false>      default true, exclude services 137-139, 1900, 3702, 5353, 5355
    -g, --gen_html <true or false>              default false, generate .html file
    -v, --version
    -h, --help

examples:
    ./blocked-traffic-flows-query.sh --ip 1.1.1.1 --exclude_services false
    ./blocked-traffic-flows-query.sh --ip 1.1.1.1 --direction dest
    ./blocked-traffic-flows-query.sh --ip 1.1.1.1 --direction dest --port 443
    ./blocked-traffic-flows-query.sh -i 1.1.1.1 -d source -p 443
EOF
}

get_config_yml(){
    source $BASEDIR/.pce_config.yml 2>/dev/null || get_pce_vars
}

get_pce_vars(){
    read -p "Enter PCE domain: " fqdn
    read -p "Enter PCE port: " port
    read -p "Enter PCE organization ID: " org
    read -p "Enter PCE API username: " user
    echo -n "Enter PCE API secret: " && read -s key && echo ""
    #improvement, ignore cert
    cat << EOF > $BASEDIR/.pce_config.yml
export fqdn=$fqdn
export port=$port
export org=$org
export user=$user
export key=$key
EOF
}

get_jq_version(){
    jq_version=$(jq --version)
    if [ $(echo $?) -ne 0 ]; then
        echo "jq application not found. jq is a commandline JSON processor and is used to process and filter JSON inputs."
        echo "https://stedolan.github.io/jq/"
        echo "Script will attempt to copy the included jq binary to /usr/bin/"
        cp ./jq /usr/bin/ || cp $BASEDIR/jq /usr/bin/ || echo -e "Please install jq, i.e. yum install jq\nor manually copy the included binary to /usr/bin/, i.e. cp ./jq /usr/bin/" && exit 1
    fi
}

get_python_version(){
    python_version=$(python --version)
    if [ $(echo $?) -ne 0 ]; then
        echo "python was not found and is required dependancy. Please install."
        exit 1
    fi
    pandas_check=$(python -c 'import pandas;' 2>/dev/null)
    if [ $(echo $?) -ne 0 ]; then
        echo "python pandas package was not found and is required dependancy. Please install, i.e. pip install pandas"
        exit 1
    fi
}

get_version(){
    echo "blocked-traffic-flows-query.sh v"$version
}

traffic_flows_query(){
    echo "" && echo "Web request body:"
    today=$(date +"%Y-%m-%dT%H:%M")
    yesterday=$(date -d "24 hours ago" +"%Y-%m-%dT%H:%M" 2>/dev/null || date -v-1d +"%Y-%m-%dT%H:%M")
    body='{"sources":{"include":[['$source_ip']],"exclude":[]},"destinations":{"include":[['$dest_ip']],"exclude":[]},"services":{"include":['$port_number'],"exclude":['$services_to_exclude']},"sources_destinations_query_op":"'$logic'","start_date":"'$yesterday'","end_date":"'$today'","policy_decisions":["blocked"],"boundary_decisions":[],"max_results":100000,"exclude_workloads_from_ip_list_query":true,"query_name":""}'
    echo $body
    echo "" && echo "Submitting query..."
    traffic_flows_query_href=$(curl -s -k https://$user:$key@$fqdn:$port/api/v2/orgs/$org/traffic_flows/async_queries -X POST -H 'content-type: application/json' --data-raw $body | jq -r .href)
    traffic_flows_query_status=''
    while [[ $traffic_flows_query_status != "completed" ]]; do
        sleep 2
        traffic_flows_query_status=$(curl -s -k https://$user:$key@$fqdn:$port/api/v2$traffic_flows_query_href | jq -r .status)
    done
    curl -s -k https://$user:$key@$fqdn:$port/api/v2$traffic_flows_query_href/download > traffic_flows_query_results.csv
    cut -d, -f1,14,26,27,31,36,37 traffic_flows_query_results.csv > parsed_traffic_flows_query_results.csv
    cat parsed_traffic_flows_query_results.csv | (sed -u 1q;sort) > unique_parsed_traffic_flows_query_results.csv
    echo "" && echo "Blocked traffic flows query results:"
    cat unique_parsed_traffic_flows_query_results.csv
    if [[ "$gen_html" == "true" ]]; then
        python -c 'import pandas;pandas.read_csv("unique_parsed_traffic_flows_query_results.csv").to_html("unique_parsed_traffic_flows_query_results.html");'
    fi
}

BASEDIR=$(dirname $0)

#improvements:
#allow to change max results
#check if ignore cert
#rulesearch if rule exists even though blocked
#logging

source_ip=
dest_ip=
direction=
logic="or"
port_number=
exclude_services=true
services_to_exclude='{"port":137},{"port":138},{"port":139},{"port":1900},{"port":3702},{"port":5353},{"port":5355}'
gen_html=false

while true
do
    if [ "$1" == "" ]; then
        break
    fi
    case $1 in
        -i|--ip)
            if [ "$2" == "" ] || [[ "$2" == -* ]]; then
                echo "ERROR: ip argument requires a parameter of an ip address"
                exit 1
            fi
            source_ip='{"ip_address":"'$2'"}'
            dest_ip=$source_ip
            shift
            ;;
        -d|--direction)
            if [ "$2" == "source" ] || [[ "$2" == "dest" ]]; then
                direction=$2
            else
                echo "ERROR: direction argument requires a parameter of either 'source' or 'dest'"
                exit 1
            fi
            shift
            ;;
        -p|--port_number)
            if [[ ! "$2" =~ ^[0-9]+$ ]]; then
                echo "ERROR: port_number argument requires a parameter of an integer"
                exit 1
            fi
            port_number='{"port":'$2'}'
            shift
            ;;
        -e|--exclude_services)
            if [ "$2" == "true" ] || [[ "$2" == "false" ]]; then
                exclude_services=$2
            else
                echo "ERROR: exclude_services argument requires a parameter of either 'true' or 'false'"
                exit 1
            fi
            shift
            ;;
        -g|--gen_html)
            if [ "$2" == "true" ] || [[ "$2" == "false" ]]; then
                gen_html=$2
            else
                echo "ERROR: gen_html argument requires a parameter of either 'true' or 'false'"
                exit 1
            fi
            shift
            ;;
        -v|--version)
            get_version
            exit 0
            ;;
        -h|--help)
            usage
            exit 1
            ;;
        -*)
            echo -e "\n$0: ERROR: Unknown option: $1" >&2
            usage
            exit 1
            ;;
        *)
            echo -e "\n$0: ERROR: Unknown argument: $1" >&2
            usage
            exit 1
            ;;
    esac
    shift
done

if [ "$direction" == "source" ]; then
    logic="and"
    dest_ip=
elif [ "$direction" == "dest" ]; then
    logic="and"
    source_ip=
fi

if [ "$exclude_services" == "false" ]; then
    services_to_exclude=
fi

get_jq_version
if [[ "$gen_html" == "true" ]]; then
    get_python_version
fi
get_config_yml
traffic_flows_query

exit 0