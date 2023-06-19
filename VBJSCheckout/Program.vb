Imports System
Imports Paytm
Imports Newtonsoft.Json
Imports System.Net

Module Program
    Sub Main(args As String())

        Dim body As Dictionary(Of String, Object) = New Dictionary(Of String, Object)()

        Dim head As Dictionary(Of String, String) = New Dictionary(Of String, String)()

        Dim requestBody As Dictionary(Of String, Object) = New Dictionary(Of String, Object)()

        Dim txnAmount As Dictionary(Of String, String) = New Dictionary(Of String, String)()
        Dim oDate As String = DateTime.Now.ToString("yyyyMMddHHmmss")
        txnAmount.Add("value", "1.00")
        txnAmount.Add("currency", "INR")

        Dim userInfo As Dictionary(Of String, String) = New Dictionary(Of String, String)()

        userInfo.Add("custId", "1")

        body.Add("requestType", "Payment")

        body.Add("mid", "MAHELS18173569865039")

        body.Add("websiteName", "WEBSTAGING") ''DEFAULT  for production

        body.Add("orderId", oDate)

        body.Add("txnAmount", "1")

        body.Add("userInfo", userInfo)

        body.Add("callbackUrl", "http://localhost:54414/SIS/OnlinePaytmResponse.aspx")

        Dim paytmChecksum As String = Checksum.generateSignature(JsonConvert.SerializeObject(body), "0mZGIBW4MzIQCN9m")

        head.Add("signature", paytmChecksum)

        requestBody.Add("body", body)

        requestBody.Add("head", head)

        Dim post_data As String = JsonConvert.SerializeObject(requestBody)

        Dim url = "https://securegw-stage.paytm.in/theia/api/v1/initiateTransaction?mid=MAHELS18173569865039&orderId=" + oDate

        Dim webRequest As HttpWebRequest = DirectCast(HttpWebRequest.Create(url), HttpWebRequest)

        webRequest.Method = "POST"

        webRequest.ContentType = "application/json"

        webRequest.ContentLength = post_data.Length

        Dim RequestWriter As New IO.StreamWriter(webRequest.GetRequestStream())

        RequestWriter.Write(post_data)

        RequestWriter.Close()

        RequestWriter.Dispose()

        Dim responseData As String = String.Empty

        Dim responseReader As New IO.StreamReader(webRequest.GetResponse().GetResponseStream())

        responseData = responseReader.ReadToEnd()

        Console.WriteLine(responseData)

        'Response.Write(post_data)
    End Sub
End Module
