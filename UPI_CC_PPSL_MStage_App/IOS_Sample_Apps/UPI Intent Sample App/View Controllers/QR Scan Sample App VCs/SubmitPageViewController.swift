//
//  SubmitPageViewController.swift
//  UPI Intent Sample App
//
//  Created by Vishrut tewatia on 30/09/24.
//

import UIKit

class SubmitPageViewController: UIViewController, UITextFieldDelegate {
    
    //MARK: IB Outlets

    @IBOutlet weak var amountLabelTextField: UITextField!
    @IBOutlet weak var amountLabel: UILabel!
    @IBOutlet weak var submitBtn: UIButton! {
        didSet {
            setBtnState(enabled: true, btn: submitBtn)
        }
    }
    
    //MARK: Variables
    
    var orderId: String? = ""
    var amount: String? = ""
    var payeeVpa: String? = ""
    var externalSerialNo: String? = ""
    var split: String? = ""
    var tapSubmit: Bool = false
    var mscanDict: [String: String]? = nil
    let loader = UIActivityIndicatorView(style: .large)
    let blurEffectView = UIVisualEffectView(effect: UIBlurEffect(style: .light))
    let timeOut: TimeInterval = 60
    weak var delegate: ModeSelectionProtocol?
    
    //MARK: Lifecycle methods
    
    override func viewDidLoad() {
        super.viewDidLoad()
        configureLabels()
        configureLoaderView()
        if tapSubmit {
            submitBtn.sendActions(for: .touchUpInside)
        }
        let tapGesture = UITapGestureRecognizer(target: self, action: #selector(dismissKeyboard))
        view.addGestureRecognizer(tapGesture)
    }
    
    override func viewWillAppear(_ animated: Bool) {
        super.viewWillAppear(animated)
        setBtnState(enabled: true, btn: submitBtn)
    }
    
    override func viewWillDisappear(_ animated: Bool) {
        super.viewWillDisappear(animated)
        if isMovingFromParent {
            if let delegate = delegate {
                delegate.resetDict()
            }
        }
    }
    
    //MARK: UI Functions
    
    @objc func dismissKeyboard() {
        view.endEditing(true)
    }
    
    func updateLabels(amount: String?, orderId: String?, payeeVpa: String?, externalSerialNo: String?, split: String?) {
        self.amount = amount
        self.orderId = orderId
        self.payeeVpa = payeeVpa
        self.externalSerialNo = externalSerialNo
        self.split = split
    }
    
    func configureLabels() {
        if let amount = amount, !amount.isEmpty {
            amountLabelTextField.text = "\(amount)"
            amountLabelTextField.isUserInteractionEnabled = false
        } else {
            amountLabelTextField.isUserInteractionEnabled = true
            amountLabel.text = "Kindly enter the amount: "
        }
    }
    
    func setBtnState(enabled: Bool, btn: UIButton) {
        btn.isUserInteractionEnabled = enabled
        btn.alpha = (enabled ? 1 : 0.5)
    }
    
    //MARK: Textfield Methods
    
    func textField(_ textField: UITextField, shouldChangeCharactersIn range: NSRange, replacementString string: String) -> Bool {
        
        let currentText = textField.text ?? ""
        
        guard let stringRange = Range(range, in: currentText) else { return false }
        let updatedText = currentText.replacingCharacters(in: stringRange, with: string)
        
        self.amount = updatedText
        return true
    }
    
    func textFieldShouldReturn(_ textField: UITextField) -> Bool {
        textField.resignFirstResponder()
        return true
    }
    
    //MARK: IBAction Functions
    
    @IBAction func submitBtnTapped(_ sender: Any) {
        
        if let amount = amount, (amount.isEmpty) {
            let specificMessage = "Amount field shouldn't be empty"
            CommonUtility.showBasicAlert(on: self, title: "Error", message: specificMessage)
            return
        }
        
        setBtnState(enabled: false, btn: submitBtn)
        showLoader()
        
        var params = [
            "orderId" : orderId ?? "",
            "amount" : amount ?? "",
            "externalSerialNo" : externalSerialNo ?? "",
            "payeeVpa" : payeeVpa ?? ""
        ] as [String : Any]
        if let split = split {
            params["split"] = split
        }
        
        guard let url = URL(string: "https://securestage.paytmpayments.com/mockbank/upiintenet/ppslmock") else {
            return
        }
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        do {
            let data = try JSONSerialization.data(withJSONObject: params, options: .prettyPrinted)
            request.httpBody = data
        } catch {
            print(error)
        }
        
        URLSession.shared.dataTask(with: request) { [weak self] (data, response, error) in
            guard let data = data, let wself = self else {return}
            
            if let jsonString = String(data: data, encoding: .utf8) {
                print(jsonString)
            }
            
            do {
                if let responseDict = try JSONSerialization.jsonObject(with: data) as? [String:Any] {
                    ///Data task runs in background thread
                    DispatchQueue.main.async {
                    self?.hideLoader()
                }
                    DispatchQueue.main.async {
                        if let transactionDetailVC = UIStoryboard(name: "Main", bundle: Bundle(for: TransactionDetailViewController.self)).instantiateViewController(withIdentifier: String(describing: TransactionDetailViewController.self)) as? TransactionDetailViewController {
                            transactionDetailVC.orderDetailsDict = responseDict
                            transactionDetailVC.modalPresentationStyle = .overCurrentContext
                            wself.navigationController?.pushViewController(transactionDetailVC, animated: true)
                        }
                    }
                } else {
                    DispatchQueue.main.async {
                        CommonUtility.showBasicAlert(on: wself, title: "Data Error", message: "Transaction has failed due to issue in response")
                        wself.setBtnState(enabled: true, btn: wself.submitBtn)
                    }
                    //MID Error
                }
            } catch {
                DispatchQueue.main.async {
                    self?.hideLoader()
                    CommonUtility.showBasicAlert(on: wself, title: "API Error", message: "There was some issue in the API call")
                    wself.setBtnState(enabled: true, btn: wself.submitBtn)
                }
                //API Request failed
                print(error)
            }
        }.resume() //Newly initialised tasks are in suspended state, hence this is required to start it
        
        DispatchQueue.main.asyncAfter(deadline: .now() + timeOut) {
            if self.loader.isAnimating {
                self.hideLoader()
                CommonUtility.showBasicAlert(on: self, title: "Error", message: "Request timed out. Please try again.") {
                    self.navigationController?.popViewController(animated: true)
                }
            }
        }
        
    }
    
    func configureLoaderView() {
        loader.center = self.view.center
        loader.transform = CGAffineTransform(scaleX: 2.0, y: 2.0)
        loader.hidesWhenStopped = true
        self.view.addSubview(loader)
        blurEffectView.frame = self.view.bounds
        blurEffectView.autoresizingMask = [.flexibleWidth, .flexibleHeight]
        blurEffectView.alpha = 0.0
        self.view.insertSubview(blurEffectView, belowSubview: loader)
    }
    
    func showLoader() {
        loader.startAnimating()
        UIView.animate(withDuration: 0.3) {
            self.blurEffectView.alpha = 0.4
        }
    }
    
    func hideLoader() {
        loader.stopAnimating()
        UIView.animate(withDuration: 0.3) {
            self.blurEffectView.alpha = 0.0
        }
    }
}
