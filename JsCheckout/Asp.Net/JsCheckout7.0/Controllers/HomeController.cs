using JsCheckout.Models;
using Microsoft.AspNetCore.Mvc;
using System.Diagnostics;
using System.Net;
using Paytm;
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;
using System.Text.RegularExpressions;

namespace JsCheckout.Controllers
{
    public class HomeController : Controller
    {
        private readonly ILogger<HomeController> _logger;

        public HomeController(ILogger<HomeController> logger)
        {
            _logger = logger;
        }

        public IActionResult Index()
        {
            var MyConfig = new ConfigurationBuilder().AddJsonFile("appsettings.json").Build();
            var env = MyConfig.GetValue<string>("PaytmSettings:ENVIRONMENT");
            var mid = MyConfig.GetValue<string>("PaytmSettings:MID");
            var mkey = MyConfig.GetValue<string>("PaytmSettings:MKEY");
            var website = MyConfig.GetValue<string>("PaytmSettings:WEBSITE");
            var orderId = "asp_" + Convert.ToString(DateTime.Now.Ticks);

            Dictionary<string, object> body = new Dictionary<string, object>();
            Dictionary<string, string> head = new Dictionary<string, string>();
            Dictionary<string, object> requestBody = new Dictionary<string, object>();

            Dictionary<string, string> txnAmount = new Dictionary<string, string>();
            txnAmount.Add("value", "1.00");
            txnAmount.Add("currency", "INR");
            Dictionary<string, string> userInfo = new Dictionary<string, string>();
            userInfo.Add("custId", "cust_001");
            body.Add("requestType", "Payment");
            body.Add("mid", mid);
            body.Add("websiteName", website);
            body.Add("orderId", orderId);
            body.Add("txnAmount", txnAmount);
            body.Add("userInfo", userInfo);
            body.Add("callbackUrl", "Home/Callback");

            /*
            * Generate checksum by parameters we have in body
            * Find your Merchant Key in your Paytm Dashboard at https://dashboard.paytm.com/next/apikeys 
            */
            string paytmChecksum = Checksum.generateSignature(JsonConvert.SerializeObject(body), mkey);

            head.Add("signature", paytmChecksum);

            requestBody.Add("body", body);
            requestBody.Add("head", head);

            string post_data = JsonConvert.SerializeObject(requestBody);

            //For  Staging
            string url = env+"theia/api/v1/initiateTransaction?mid="+mid+"&orderId="+ orderId;

            HttpWebRequest webRequest = (HttpWebRequest)WebRequest.Create(url);

            webRequest.Method = "POST";
            webRequest.ContentType = "application/json";
            webRequest.ContentLength = post_data.Length;

            using (StreamWriter requestWriter = new StreamWriter(webRequest.GetRequestStream()))
            {
                requestWriter.Write(post_data);
            }

            string responseData = string.Empty;

            using (StreamReader responseReader = new StreamReader(webRequest.GetResponse().GetResponseStream()))
            {
                responseData = responseReader.ReadToEnd();
                Console.WriteLine(responseData);
            }

            JObject jObject = JObject.Parse(responseData);
            string displayName = jObject.SelectToken("body.txnToken").Value<string>();
            ViewBag.txnToken = displayName;
            ViewBag.orderId = orderId;
            ViewBag.amount = "1.00";
            ViewBag.url = env+ "/merchantpgpui/checkoutjs/merchants/"+ mid + ".js";
            return View();
        }

        public IActionResult Privacy()
        {
            return View();
        }

        public IActionResult Callback()
        {

            var MyConfig = new ConfigurationBuilder().AddJsonFile("appsettings.json").Build();
            var mkey = MyConfig.GetValue<string>("PaytmSettings:MKEY");
            string paytmChecksum = "";
            string checkSumMatch = "fail";
            Dictionary<string, string> parameters = new Dictionary<string, string>();
            if (Request.Form.Keys.Count > 0)
            {
                foreach (string key in Request.Form.Keys)
                {
                    if (Request.Form[key].Contains("|"))
                    {
                        parameters.Add(key.Trim(), "");
                    }
                    else
                    {
                        parameters.Add(key.Trim(), Request.Form[key]);
                    }
                }

                if (parameters.ContainsKey("CHECKSUMHASH"))
                {
                    paytmChecksum = parameters["CHECKSUMHASH"];
                    parameters.Remove("CHECKSUMHASH");
                }
                if (!string.IsNullOrEmpty(paytmChecksum) && Checksum.verifySignature(parameters, mkey, paytmChecksum))
                {
                    checkSumMatch = "success";
                }
            }
            ViewBag.checkSumMatch = checkSumMatch;
            return View();
        }

        [ResponseCache(Duration = 0, Location = ResponseCacheLocation.None, NoStore = true)]
        public IActionResult Error()
        {
            return View(new ErrorViewModel { RequestId = Activity.Current?.Id ?? HttpContext.TraceIdentifier });
        }

    }
}