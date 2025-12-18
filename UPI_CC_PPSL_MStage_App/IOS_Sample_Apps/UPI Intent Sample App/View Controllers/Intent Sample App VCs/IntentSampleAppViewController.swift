//
//  ViewController.swift
//  UPI Intent Sample App
//
//  Created by Vishrut tewatia on 19/09/23.
//

import UIKit

class IntentSampleAppViewController: UIViewController, UITableViewDelegate, UITableViewDataSource {
    
    //MARK: Outlets
    
    @IBOutlet weak var successfulTransactionBtn: UIButton! {
        didSet {
            successfulTransactionBtn.setImage(UIImage(named: "radioButton_Selected"), for: .selected)
            successfulTransactionBtn.setImage(UIImage(named: "radioButton"), for: .normal)
        }
    }
    @IBOutlet weak var failedTransactionBtn: UIButton! {
        didSet {
            failedTransactionBtn.setImage(UIImage(named: "radioButton_Selected"), for: .selected)
            failedTransactionBtn.setImage(UIImage(named: "radioButton"), for: .normal)
        }
    }
    @IBOutlet weak var doneBtn: UIButton!
    @IBOutlet weak var vpaTextField: UITextField!
    @IBOutlet weak var textFieldView: UIView!
    @IBOutlet weak var failureReasonBtn: UIButton!
    @IBOutlet weak var tableView: UITableView!
    
    //MARK: Variables
    
    var externalSerialNumber: String?
    var deepLinkUrl: String?
    var amount: String?
    
    var errorDict = [String]()
    var selectedError = ""
    var isDropdownVisible = false
    
    //MARK: Lifecycle Methods
    
    override func viewDidLoad() {
        super.viewDidLoad()
        successfulTransactionBtn.isSelected = false
        failedTransactionBtn.isSelected = false
        vpaTextField.isUserInteractionEnabled = false
        textFieldView.layer.cornerRadius = 8.0
        textFieldView.layer.masksToBounds = true
        textFieldView.layer.borderWidth = 1.0
        textFieldView.layer.borderColor = CGColor(red: 0, green: 0, blue: 0, alpha: 0.2)
        doneBtn.layer.cornerRadius = 8.0
        tableView.delegate = self
        tableView.dataSource = self
        tableView.isHidden = true
        tableView.register(UINib(nibName: "DropDownTableViewCell", bundle:  Bundle(for: IntentSampleAppViewController.self)), forCellReuseIdentifier: "DropDownTableViewCell")
        failureReasonBtn.isEnabled = false
        initialiseErrorDict()
    }
    
    
    // MARK: Internal Methods
    
    func initialiseErrorDict() {
        errorDict = ["U30:DEBIT_HAS_BEEN_FAILED", "U90:#N/A", "U67:DEBIT_TIMEOUT", "U30-Z9:DF_INSUFFICIENT_FUNDS_IN_REMITTER_ACCOUNT", "U30-ZM:DF_INVALID_MPIN", "INT-1452:PSP_NOT_AVAILABLE", "U16:RISK_THRESHOLD_EXCEEDED", "T01:TXN_NOT_PRESENT", "U30-Z7:DF_REMITTER_TXN_FREQ_LIMIT_EXCEEDED", "U67-UT:RESPPAY_DEBIT_TIMEOUT", "U30-Z6:DF_NUMBER_OF_PIN_TRIES_EXCEEDED"]
    }
    
    func changeButtonTitle(title: String) {
        UIView.performWithoutAnimation {
            self.failureReasonBtn.setTitle(title, for: .normal)
            self.failureReasonBtn.layoutIfNeeded()
        }
    }
    
    func handleDeepLinkParams(extSerialNo: String?, url: String?, amount: String?) {
        self.externalSerialNumber = extSerialNo
        self.deepLinkUrl = url
        self.amount = amount
    }
    
    func createParamsForTransaction(transactionType: String, jwtToken: String) -> [String:Any] {
        let separatedError = selectedError.components(separatedBy: ":")
        var bodyParams = [
            "header" : [
                "clientId": "test-client",
                "version": "v1",
                "requestTimestamp": 1591853554776,
                "signature": jwtToken
            ],
            "body" : [
                "channelCode": "PGPTM",
                "mid": "",
                "orderId": "",
                "mobileNumber": "7777777777",
                "txnStatus": transactionType,
                "bankRRN": "123456",
                "settlementType": "DEFERRED_SETTLEMENT"
            ]
        ]
        
        if var body = bodyParams["body"] {
            
            if transactionType == "SUCCESS" {
                body["responseCode"] = "00"
                body["responseMessage"] = "\"Transaction  is successful\""
            } else {
                body["responseCode"] = separatedError[0]
                body["responseMessage"] = separatedError[1]
            }
            
            if let externalSerialNumber = externalSerialNumber{
                body["externalSerialNo"] = externalSerialNumber
            }
            
            if let amount = amount {
                body["amount"] = amount
            }
            bodyParams["body"] = body
        }
        
        return bodyParams
    }
    
    func payload(transactionType: String) -> [String:Any] {
        let separatedError = selectedError.components(separatedBy: ":")
        var payload = [
                "channelCode": "PGPTM",
                "txnStatus": transactionType,
                "bankRRN": "123456",
                "iss": "ts"
        ]
        
        if transactionType == "SUCCESS" {
            payload["responseCode"] = "00"
            payload["responseMessage"] = "\"Transaction  is successful\""
        } else {
            payload["responseCode"] = separatedError[0]
            payload["responseMessage"] = separatedError[1]
        }
        
        if let externalSerialNumber = externalSerialNumber {
            payload["externalSerialNo"] = externalSerialNumber
        }
        
        if let amount = amount {
            payload["amount"] = amount
        }
        
        return payload
    }
    
    func showAlert(message: String?) {
        let alert = UIAlertController(title: "Alert!", message: message, preferredStyle: .alert)
        alert.addAction(UIAlertAction(title: "Okay", style: .default, handler: nil))
        DispatchQueue.main.async {
            self.present(alert, animated: true, completion: nil)
        }
    }
    
    func printResponse(_ dict: [String:Any]?, for api: String) {
        print("🔶 \(api) Response")
        print(dict ?? [:])
        print("--------------------")
    }
    
    func resetInitialisationParams() {
        self.externalSerialNumber = ""
    }
    
    private func toggleDropdown() {
        isDropdownVisible.toggle()
        tableView.isHidden = !isDropdownVisible
    }
    
    //MARK: Tableview Methods
    
    func tableView(_ tableView: UITableView, numberOfRowsInSection section: Int) -> Int {
        errorDict.count
    }
    
    func tableView(_ tableView: UITableView, cellForRowAt indexPath: IndexPath) -> UITableViewCell {
        let cell = tableView.dequeueReusableCell(withIdentifier: "DropDownTableViewCell", for: indexPath) as! DropDownTableViewCell
        cell.configureCell(label: self.errorDict[indexPath.row])
        return cell
    }
    
    func tableView(_ tableView: UITableView, didSelectRowAt indexPath: IndexPath) {
        selectedError = errorDict[indexPath.row]
        print("Selected Option: \(selectedError)")
        changeButtonTitle(title: selectedError)
        toggleDropdown()
    }
    
    func tableView(_ tableView: UITableView, heightForRowAt indexPath: IndexPath) -> CGFloat {
        return UITableView.automaticDimension
    }
    
    func tableView(_ tableView: UITableView, estimatedHeightForRowAt indexPath: IndexPath) -> CGFloat {
        return 40
    }

    //MARK: Action Methods
    
    @IBAction func initiateTransaction(_ sender: Any) {
        
        var transactionType = ""
        
        if successfulTransactionBtn.isSelected == true {
            transactionType = "SUCCESS"
        } else if failedTransactionBtn.isSelected == true {
            if failureReasonBtn.titleLabel?.text == "Select Failure Reason" {
                showAlert(message: "Please select failure reason")
                return
            }
            transactionType = "FAIL"
        } else {
            showAlert(message: "Please select transaction type")
            return
        }
        
        let requestedJwtToken = UPIIntentSampleAppUtilities.getJWTToken(payload: payload(transactionType: transactionType), clientSecret: "267367cjdbcjdbc6256cjbcj2727d")
        let bodyParams = self.createParamsForTransaction(transactionType: transactionType, jwtToken: requestedJwtToken)
        
        guard let url = URL(string: "https://securegw-stage.paytm.in/instaproxy/secureresponse/PPBL/UPI/PUSH/RESP") else {
            return
        }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        do {
            let data = try JSONSerialization.data(withJSONObject: bodyParams, options: .prettyPrinted)
            request.httpBody = data
        } catch{
            print("🔴", error)
        }
        
        URLSession.shared.dataTask(with: request) { (data, response, error) in
            
            guard let data = data else {
                return
            }
            
            do {
                if let jsonDict = try JSONSerialization.jsonObject(with: data) as? [String: Any] {
                    self.printResponse(jsonDict, for: "Staging Instaproxy")
//                    self.resetInitialisationParams()
                    //TODO: Ask if above reset is applicable otherwise same instance will make multiple successful instaproxy hits
                }
            }
            catch {
                print("🔴", error)
            }
        }.resume()
    }
    
    @IBAction func didTapSuccessfulTransactionBtn(_ sender: Any) {
        successfulTransactionBtn.isSelected = !successfulTransactionBtn.isSelected
        failedTransactionBtn.isSelected = false
        tableView.isHidden = true
        changeButtonTitle(title: "Select Failure Reason")
        failureReasonBtn.isEnabled = false
    }
    
    @IBAction func didTapFailedTransactionBtn(_ sender: Any) {
        failedTransactionBtn.isSelected = !failedTransactionBtn.isSelected
        successfulTransactionBtn.isSelected = false
        failureReasonBtn.isEnabled = true
    }
    
    @IBAction func failureReasonBtnTapped(_ sender: Any) {
        toggleDropdown()
    }
    
}
